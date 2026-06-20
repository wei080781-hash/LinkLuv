# 刪除訊息功能實作指南 (LinkLuv)

本文件紀錄了在 LinkLuv 專案中實現「訊息刪除」功能的規劃與實作邏輯。

## 1. 資料庫層面
*   **軟刪除 (Soft Delete)**: 建議在 `messages` 資料表中引入 `deleted_at` 欄位。
    *   建立 migration 檔案：`php artisan make:migration add_deleted_at_to_messages_table --table=messages`
    *   內容 (`up` 與 `down` 方法)：
        ```php
        public function up(): void
        {
            Schema::table('messages', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        public function down(): void
        {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
        ```

## 2. 資料庫級聯刪除 (OnDelete Cascade) - 極致效能方案
若您希望刪除父留言時，其下所有子留言能自動一併被刪除，且要求「最高執行效率」，強烈建議直接在資料庫層級設定級聯刪除。

*   **實作方法**:
    *   **情境 A：若您正在「建立」新資料表 (`Schema::create`)**:
        ```php
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->text('content');
            // 直接在 create 區塊內加入
            $table->foreignId('parent_id')->nullable()->constrained('messages')->onDelete('cascade');
            $table->timestamps();
        });
        ```
    *   **情境 B：若資料表「已存在」，需建立新 Migration (`Schema::table`)**:
        使用 `php artisan make:migration add_parent_id_to_messages_table --table=messages` 建立檔案：
        ```php
        public function up(): void
        {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->constrained('messages')->onDelete('cascade');
            });
        }
        ```

*   **優勢**:
    *   **效率極致**: 由資料庫引擎直接處理刪除動作，無需 PHP 迴圈，亦無 N+1 查詢問題。
    *   **程式精簡**: 控制器中只需執行 `$message->delete()`，無需額外編寫遞迴邏輯。

> **注意**: 若您已啟用軟刪除 (`SoftDeletes`)，請確保在使用級聯刪除時，您的資料庫遷移設定與軟刪除的行為相容。若為傳統硬刪除，此方案為最佳選擇。

---

## 3. Model 設定 (app/Models/Message.php)
*   **啟用 Trait**: 
    ```php
    use Illuminate\Database\Eloquent\SoftDeletes;

    class Message extends Model
    {
        use HasFactory, SoftDeletes;
        // ...
    }
    ```

## 3. 路由定義 (routes/web.php)
*   **註冊 DELETE 路由**:
    您可以將路由直接寫在 `routes/web.php` 的結尾處，或是整合進受保護的群組內以提升安全性。

    **建議整合進 auth 群組 (推薦)**:
    ```php
    Route::middleware('auth')->group(function () {
        // ... 其他路由
        Route::delete('/messages/{message}', [App\Http\Controllers\MessageController::class, 'destroy'])
            ->name('messages.destroy');
    });
    ```

    **或者直接寫在檔案底部**:
    ```php
    Route::delete('/messages/{message}', [App\Http\Controllers\MessageController::class, 'destroy'])
        ->name('messages.destroy');
    ```

> **注意**: 若將路由放置在檔案底部（全域範圍），請務必在 `MessageController` 的 `destroy` 方法中手動進行 `auth()` 檢查，否則未登入者也能呼叫該 API。目前控制器範例已包含檢查，故放在全域是安全的。

## 4. 控制器實作 (app/Http\Controllers\MessageController.php)
*   **新增刪除方法**:
    ```php
    public function destroy(Message $message)
    {
        // 權限檢查
        if ($message->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => '無權刪除'], 403);
        }

        $message->delete();

        return response()->json(['success' => true]);
    }
    ```

## 5. 前端實作 (resources/views/feed.blade.php)
*   **JavaScript 刪除函式**:
    請將以下函式放置在 `resources/views/feed.blade.php` 的 `<script>` 區塊內，建議與 `toggleReply` 或 `toggleLike` 等其他功能函式並列，確保其作用域正確：

    ```javascript
    function deleteMessage(messageId) {
        if (!confirm('確定要刪除這則訊息嗎？')) return;

        fetch(`/messages/${messageId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                loadMessages(); // 刪除成功後重新載入列表
            } else {
                alert(data.message || '刪除失敗');
            }
        });
    }
    ```

## 7. 常見問題與故障排除

### 7.1 錯誤訊息：SQLSTATE[42S22]: Column not found: 1054 Unknown column 'messages.deleted_at'
*   **問題原因**: 在 `Message` 模型中使用了 `use SoftDeletes;` Trait，但資料庫的 `messages` 表中尚不存在 `deleted_at` 欄位。Laravel 會自動在查詢時加上 `where deleted_at is null`，導致查詢失敗。
*   **解決方法**: 
    1. 確保已建立包含 `softDeletes()` 的 Migration 檔案。
    2. 在終端機執行 `php artisan migrate` 以更新資料庫結構。
    3. 若確認資料庫表結構已更新但仍報錯，請執行 `php artisan optimize:clear` 清除快取。

### 7.3 錯誤訊息：SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name
*   **問題原因**: 當執行 `php artisan migrate:fresh` 時，多個 Migration 檔案中重複定義了相同的欄位（例如 `parent_id` 同時出現在 `create_messages_table` 與 `add_parent_id_...` 檔案中）。
*   **解決方法**: 
    1. **檢查 Migration 順序與內容**: 確認各檔案是否重複定義了欄位。
    2. **移除冗餘檔案**: 若欄位已整合至主建表檔案 (`create_messages_table`)，應將後續個別新增欄位的 Migration 檔案刪除或移出 `database/migrations` 資料夾。
    3. **重新執行**: 執行 `php artisan migrate:fresh` 確保資料庫架構與最新版本的 Migration 檔案同步。

### 7.4 錯誤訊息：Could not open input file: artisan
*   **問題原因**: 在非專案根目錄下執行了 `php artisan` 指令。
*   **解決方法**: 
    1. 使用 `cd` 指令切換至專案根目錄（包含 `artisan` 檔案的目錄）。
    2. 或使用相對路徑執行：`php ..\..\artisan migrate`。

---
## 6. 關鍵實作要點總結
1.  **資料庫**: 執行 migration 後務必執行 `php artisan migrate`。
2.  **安全性**: 務必檢查 `auth()->id()` 以防越權刪除。
3.  **UI**: 刪除後呼叫 `loadMessages()` 確保介面狀態與資料庫同步。
