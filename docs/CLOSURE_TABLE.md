# 閉包表模型 (Closure Table) 實作指南 (LinkLuv)

在 LinkLuv 專案中，若需要高效地查詢留言樹狀結構（例如一次撈出 30 號留言及其所有後代），推薦使用閉包表模型。

## 1. 資料庫設計 (Migration)
請建立一個新的遷移檔案 `php artisan make:migration create_message_closure_table`，並填入以下內容：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_closure', function (Blueprint $table) {
            // ancestor 是祖先，descendant 是後代
            $table->foreignId('ancestor')->constrained('messages')->onDelete('cascade');
            $table->foreignId('descendant')->constrained('messages')->onDelete('cascade');
            $table->primary(['ancestor', 'descendant']); // 確保不重複的關係鏈
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_closure');
    }
};
```

## 2. Controller 整合實作 (MessageController.php)
請將以下方法新增至 `app/Http/Controllers/MessageController.php` 類別中，並在 `store` 方法建立留言後呼叫。

### 步驟 A：新增私有方法
將 `storeClosure` 方法放在 Controller 的類別大括號內（建議放在 `store` 方法之後）：

```php
private function storeClosure($descendantId, $parentId) 
{
    // 1. 建立自身關聯
    \DB::table('message_closure')->insert(['ancestor' => $descendantId, 'descendant' => $descendantId]);
    
    // 2. 繼承父留言的所有祖先關係
    $ancestors = \DB::table('message_closure')->where('descendant', $parentId)->get();
    foreach ($ancestors as $a) {
        \DB::table('message_closure')->insert([
            'ancestor' => $a->ancestor,
            'descendant' => $descendantId
        ]);
    }
}
```

### 步驟 B：在 store 方法中呼叫
在 `Message::create` 建立留言後，請將邏輯插入到 `store` 方法中。以下是正確的位置參考：

```php
public function store(Request $request)
{
    // ... 前面的驗證與邏輯 ...

    // 1. 建立留言
    $message = Message::create([
        'content' => $request->content,
        'user_id' => auth()->id(),
        'parent_id' => $request->parent_id,
        'depth' => $depth,
        'image_path' => $imagePath,
    ]);

    // --- 在這裡貼上你的邏輯 ---
    if ($request->parent_id) {
        $this->storeClosure($message->id, $request->parent_id);
    } else {
        // 若為第一層留言，僅建立自身關聯
        \DB::table('message_closure')->insert([
            'ancestor' => $message->id, 
            'descendant' => $message->id
        ]);
    }
    // ------------------------

    return response()->json(['success' => true]);
}
```


## 3. 核心查詢 (一次撈出所有子孫)
使用此架構，查詢留言 30 號的所有後代（包含回覆的回覆）：

```sql
SELECT m.* 
FROM messages m
JOIN message_closure mc ON m.id = mc.descendant
WHERE mc.ancestor = 30;
```

## 4. 優勢分析
*   **查詢效能**：無論樹多深，查詢後代皆為單一 Join 操作。
*   **靈活性**：可輕鬆處理無限層級，且不影響其他留言節點。
*   **維護性**：刪除節點時，只需刪除所有 `ancestor` 或 `descendant` 等於該 ID 的記錄，非常簡單。
