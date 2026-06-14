# 留言系統 - 點讚功能開發指南 (LinkLuv)

本指南記錄了在 LinkLuv 專案中實作「點讚 (Like)」功能的完整開發步驟、遇到的問題與最終解決方案。

---

## 1. 資料庫設計
需要新增一個關聯表 `likes` 來記錄使用者與留言的對應關係。

*   **定義 Schema (database/migrations/xxxx_create_likes_table.php)**:
    ```php
    public function up() {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'message_id']); // 防止重複點讚
        });
    }
    ```

## 2. Model 關聯建立
在 `app/Models/Message.php` 與 `Like.php` 中建立關聯：

*   **建立 Model (app/Models/Like.php)**:
    ```php
    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class Like extends Model
    {
        protected $fillable = ['user_id', 'message_id'];

        public function user() { return $this->belongsTo(User::class); }
        public function message() { return $this->belongsTo(Message::class); }
    }
    ```
    *建立後請執行 `composer dump-autoload`。*

*   **Message.php**:
    ```php
    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use App\Models\User;
    use App\Models\Like;

    class Message extends Model
    {
        use HasFactory;

        protected $fillable = ['content', 'user_id', 'parent_id', 'depth', 'image_path'];

        public function likes() {
            return $this->hasMany(Like::class);
        }
    }
    ```

## 3. 控制器邏輯 (MessageController.php)

確保 `index` 方法回傳的資料包含點讚總數與狀態，`like` 方法處理切換邏輯。

### 3.1 完整實作代碼
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\Like;

class MessageController extends Controller
{
    // 獲取留言列表 (包含點讚統計與目前使用者點讚狀態)
    public function index()
    {
        $userId = auth()->id();
        
        return Message::with(['user'])
            ->withCount('likes') // 自動產生 likes_count 欄位
            ->withExists(['likes as is_liked' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }]) // 自動產生 is_liked (boolean) 欄位
            ->orderBy('created_at', 'asc')
            ->get();
    }

    // 處理點讚行為
    public function like(Message $message) 
    {
        $userId = auth()->id();
        $like = $message->likes()->where('user_id', $userId)->first();
        
        if ($like) {
            $like->delete(); // 取消點讚
            $isLiked = false;
        } else {
            $message->likes()->create(['user_id' => $userId]); // 點讚
            $isLiked = true;
        }

        return response()->json([
            'success' => true, 
            'liked' => $isLiked,
            'likes_count' => $message->likes()->count()
        ]);
    }
}
```

## 4. 前端顯示 (feed.blade.php 與 JS)

在 JavaScript 渲染留言的區域，使用從後端接收到的 `msg.likes_count` 與 `msg.is_liked`：

```javascript
// JS 渲染範例
let likeColor = msg.is_liked ? 'text-pink-600' : 'text-gray-400';
let html = `
    <button onclick="toggleLike(${msg.id})" class="text-xs ${likeColor}">
        ❤️ ${msg.likes_count}
    </button>
`;
```

### 4.1 操作區平行排列佈局 (整合建議)
為了將「回覆」、「刪除」與「讚」三個按鈕水平對齊，必須將它們置入同一個 `flex` 容器中：

```html
<!-- 單一 flex 容器將三個功能平行排列 -->
<div class="flex items-center gap-4 mt-3 border-t pt-2">
    <!-- 回覆 -->
    <button onclick="toggleReply(${msg.id})" class="text-xs text-gray-500 hover:text-blue-600">回覆</button>

    <!-- 刪除 -->
    <button onclick="deleteMessage(${msg.id})" class="text-xs text-gray-500 hover:text-red-600">刪除</button>

    <!-- 點讚 (狀態綁定樣式) -->
    <button onclick="toggleLike(${msg.id})" class="flex items-center gap-1 text-xs ${msg.is_liked ? 'text-pink-600' : 'text-gray-400'}">
        <span>❤️</span> <span>${msg.likes_count}</span>
    </button>
</div>
```

**調整重點：**
*   **容器合併**: 將所有按鈕放入同一個 `div` 中，並設定 `flex items-center gap-4`，這是實現平行排列的關鍵。
*   **DOM 結構**: 刪除了原本將「讚」放在容器外的錯誤邏輯，將其與其他操作按鈕視為同一層級的元件。

---

ˊ## 5. 開發遇到的問題與解決記錄

### 5.1 點讚數顯示為 `undefined`
*   **問題原因**: 前端 JavaScript 嘗試讀取 `likes_count`，但後端 API 回傳的 JSON 資料中未包含該欄位。
*   **完整解決方案**: 
    1. **後端修正**: 在 `MessageController@index` 的查詢鏈中，務必加入 `->withCount('likes')`。
    2. **狀態同步**: 加入 `->withExists(['likes as is_liked' => ...])` 來確保 `is_liked` 欄位被正確傳遞。
    3. **緩存清理**: 每次修改 Controller 後，務必執行 `php artisan optimize:clear`，否則 Laravel 可能會使用舊的編譯設定，導致 JSON 結構未更新。

### 5.2 遭遇 `Parse Error: unexpected T_STRING`
*   **問題原因**: 語法錯誤或編碼問題。
    1. 代碼中存在語法錯誤 (如在閉包中使用錯誤的變數名稱 `$squery`，應為 `$query`)。
    2. 檔案編碼不正確 (檔案以 `UTF-8 with BOM` 存檔)。
    3. 複製程式碼時不慎將編輯器的「行號」一併複製貼入檔案。
*   **完整解決方案**: 
    1. 使用 VS Code 或專業編輯器，點擊右下角編碼將檔案轉存為 **純 `UTF-8`** (不帶 BOM)。
    2. 檢查檔案內容，刪除所有不屬於程式碼的數字行號。
    3. 執行 `php -l 檔案路徑` 來精確定位語法錯誤行。
    4. 若環境混亂，執行 `composer dump-autoload` 重置類別映射。

## 6. 驗證與測試
使用 `php artisan tinker` 驗證資料庫寫入：
```php
\App\Models\Like::all(); // 確認資料筆數與內容
```
執行測試：
```bash
php artisan test --filter MessageLikeTest
```
