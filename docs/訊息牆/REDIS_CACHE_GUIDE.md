# 🚀 LinkLuv 訊息牆 Redis 快取實作全手冊 (完整版)

本手冊旨在引導您將訊息牆 API 升級為 **Redis 快取架構**，實現「公用資料極速讀取」與「個人化點讚精確顯示」的完美結合。

## 第一階段：環境基礎建設 (Infrastructure)

### 1. 安裝 Redis 伺服器
*   **下載**：前往 [Redis for Windows](https://github.com/tporadowski/redis/releases) 下載最新的 `.msi` 安裝檔。
*   **啟動**：安裝完成後，確認服務已啟動，或手動執行 `redis-server.exe`。
*   **驗證**：開啟終端機輸入：
    ```bash
    redis-cli ping
    ```
    *應回傳：`PONG`*

### 2. 開啟 PHP Redis 擴展
*   **編輯 php.ini**：開啟 `C:\xampp\php\php.ini`。
*   **尋找擴展**：搜尋 `extension=redis`。
    *   若前面有分號 `;`，請刪除它。
    *   若找不到，請在檔案末尾加入一行：`extension=redis`。
*   **重啟伺服器**：打開 XAMPP Control Panel，點擊 Apache 的 **Stop** 再 **Start**。

### 3. 配置 Laravel 環境 (`.env`)
修改您的 `.env` 檔案，確保快取驅動指向 Redis：
```env
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```
*修改後執行：`php artisan config:clear`*

---

## 第二階段：後端程式碼實作 (Controller)

請編輯 `app/Http/Controllers/MessageController.php`。

### 1. 引入必要門面 (Top of File)
```php
use Illuminate\Support\Facades\Cache;
```

### 2. 實作 `index` 方法 (智能快取讀取)
**核心邏輯**：快取「訊息主體」，動態運算「點讚狀態」。
```php
public function index()
{
    // 1. 從 Redis 讀取全域訊息快取 (若無則查詢 DB 並存入 1 小時)
    $messages = Cache::remember('global_messages_feed', 3600, function () {
        return Message::with(['user'])
            ->withCount('likes')
            ->orderBy('path', 'ASC')
            ->get();
    });

    // 2. 獲取目前用戶的點讚清單 (不快取，因為每個人不同)
    $likedIds = auth()->check() 
        ? auth()->user()->likes()->pluck('message_id', 'message_id')->toArray() 
        : [];

    // 3. 在記憶體中動態合併個人化狀態
    return $messages->map(function ($msg) use ($likedIds) {
        $msg->is_liked = isset($likedIds[$msg->id]);
        return $msg;
    });
}
```

### 3. 實作快取失效邏輯 (Invalidation)
只要資料有任何變動，就必須手動清除快取，確保下一位使用者抓到最新資料。
**在以下方法的 `return` 之前加入 `Cache::forget('global_messages_feed');`：**
*   `store()` / `update()` / `destroy()` / `like()`

---

## 第三階段：驗證與排錯 (Testing)

### 1. 用戶端行為測試 (Black-box Testing)
| 步驟 | 操作 | 預期現象 |
| :--- | :--- | :--- |
| **1** | 執行 `php artisan cache:clear` | 清空所有舊資料，準備乾淨測試。 |
| **2** | 刷新訊息牆頁面 | 第一次讀取稍慢，日誌顯示有 SQL 查詢。 |
| **3** | 再次刷新頁面 | **極速載入**，日誌中不應出現大量訊息查詢 SQL。 |
| **4** | 使用 A 帳號點讚 | 頁面讚數立即更新（快取被 forget 並重新產生）。 |
| **5** | 切換 B 帳號登入 | B 看到讚數正確，但紅心是空的（驗證個人化邏輯）。 |

### 2. 技術深度驗證 (White-box Testing)
當您不確定快取是否真的在運作時，請執行以下兩步驗證：

#### A. 應用層驗證 (`php artisan tinker`)
驗證 Laravel 框架是否能正確讀取並還原快取資料。
```php
// 1. 嘗試讀取快取 Key
$data = Cache::get('global_messages_feed');

// 2. 檢查是否拿到 Collection 物件
$data[0]->content; // 應回傳第一則留言的內容
$data[0]->user->name; // 應回傳用戶名稱
```
*   **成功標準**：能直接讀出留言內容且不報錯，證明 Laravel $\leftrightarrow$ Redis 通路暢通。

#### B. 儲存層驗證 (`redis-cli`)
驗證 Redis 伺服器記憶體中是否真的存在該資料（跳過 Laravel）。
```bash
# 1. 進入 Redis 伺服器
redis-cli

# 2. 切換到快取資料庫 (預設通常是 1)
SELECT 1

# 3. 列出所有 Key
KEYS *

# 4. 查看特定 Key 的內容
GET linkluvproject-database-linkluvproject-cache-global_messages_feed
```
*   **成功標準**：看到一串以 `O:39:"Illuminate...` 開頭的長字串。
*   **技術說明**：這是 **序列化 (Serialization)** 後的結果。Redis 僅儲存字串，所以 Laravel 將 PHP 物件「壓扁」儲存，讀取時再「還原」。看到這串亂碼反而證明資料已正確實體化在記憶體中。

### 3. 常見問題排除
*   **修改後沒反應？**：請執行 `php artisan config:clear` 並重啟 `php artisan serve`。
*   **報錯 `Class Redis not found`？**：請確認 `php.ini` 的擴展已開啟並重啟 Apache。
*   **所有人的紅心都長一樣？**：請檢查 `index` 方法，確保 `is_liked` 是在 `Cache::remember` **之外**透過 `map()` 運算的。

---

## 💡 為什麼這個步驟是正確的？

1.  **效能最大化**：將最重的 SQL 查詢（樹狀結構、User 關聯、Likes 計數）交給 Redis。
2.  **資料一致性**：透過 `Cache::forget` 確保「只要資料庫動了，快取就重做」，用戶永遠看不到過期資料。
3.  **全鏈路可驗證**：透過 `Tinker` (應用層) 與 `Redis-CLI` (儲存層) 的雙重檢查，消除所有對快取失效的疑慮。
4.  **架構解耦**：將「全域資料」與「私有資料」分開處理，這是處理高流量網站的標準做法。
