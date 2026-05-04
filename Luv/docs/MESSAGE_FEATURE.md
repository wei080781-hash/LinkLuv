# 留言系統 - 核心功能開發指南 (LinkLuv)

本指南紀錄了在 LinkLuv 專案中實現「留言、階層式回覆、圖片上傳與點讚」系統的完整開發方案與錯誤排查指南。

---

## 1. 常見錯誤排查：Uncaught SyntaxError
當你在瀏覽器開發者工具 (F12) 的 Network 分頁看到請求 URL 包含 `_token=...` 且出現 **`Uncaught SyntaxError`** 時，通常是因為：

### 錯誤原因
1. **後端回應格式錯誤**：`fetch` 期望收到 JSON 格式，但後端回傳了 HTML 網頁（如登入頁），導致 JavaScript 無法解析 JSON。
2. **路由重導向 (Redirect)**：Laravel 因驗證失敗（如 419）將請求重導向，導致 `fetch` 接收到 HTML 代碼。

### 解決方法
1. **檢查 Network 回應**：點擊該失敗請求，查看 **Response** 分頁，確認是否為 HTML 代碼。
2. **控制器檢查**：確保 `MessageController@store` 最後回傳的是 JSON (`return response()->json(['success' => true]);`)。
3. **移除無效 Header**：**嚴禁**在 `fetch` 的 `headers` 中手動設定 `'Content-Type': 'application/json'`，否則 `FormData` 無法正確上傳檔案，這會導致驗證失敗引發 419 錯誤。

---

## 2. 前端渲染與提交邏輯 (feed.blade.php)
這是修正後的完整 JavaScript 程式碼，確保留言與圖片上傳功能運作正常：

```javascript
<script>
    // 渲染留言
    function renderMessages(messages) {
        const list = document.getElementById('messages-list');
        list.innerHTML = '';
        messages.forEach(msg => {
            const marginClass = (msg.depth >= 1) ? 'ml-12' : 'ml-0';
            const imageHtml = msg.image_path ? `<div class="mt-3"><img src="/storage/${msg.image_path}" class="max-w-xs rounded-xl shadow-sm border border-gray-100"></div>` : '';
            const html = `
                <div id="message-${msg.id}" class="${marginClass} mt-4">
                    <div class="p-4 bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <p class="text-xs text-gray-500 mb-1">${msg.user.name}</p>
                        <p class="text-sm text-gray-800">${msg.content}</p>
                        ${imageHtml}
                        <div class="flex items-center gap-4 mt-3 border-t pt-2">
                            <button onclick="toggleReply(${msg.id})" class="text-xs text-gray-500 hover:text-blue-600">回覆</button>
                            <button onclick="deleteMessage(${msg.id})" class="text-xs text-gray-500 hover:text-red-600">刪除</button>
                            <button onclick="toggleLike(${msg.id})" class="flex items-center gap-1 text-xs ${msg.is_liked ? 'text-pink-600' : 'text-gray-400'}">
                                <span>❤️</span> <span>${msg.likes_count || 0}</span>
                            </button>
                        </div>
                    </div>
                    <div id="reply-form-${msg.id}" class="hidden mt-2 ${marginClass}">
                        <form onsubmit="submitReply(event, ${msg.id})" class="flex gap-2">
                            <input type="hidden" name="parent_id" value="${msg.id}">
                            <input type="text" name="content" required placeholder="回覆..." class="rounded-full text-sm px-4 py-1.5 border w-full">
                            <button type="submit" class="text-pink-600 text-sm font-bold px-3">送出</button>
                        </form>
                    </div>
                </div>`;
            list.insertAdjacentHTML('beforeend', html);
        });
    }

    // 發表留言 (確保沒有 Content-Type)
    function submitPost(event) {
        event.preventDefault();
        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(event.target),
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(data => { if(data.success) { loadMessages(); event.target.reset(); } })
        .catch(err => console.error('Fetch Error:', err));
    }

    // 回覆留言 (確保沒有 Content-Type)
    function submitReply(e, parentId) {
        e.preventDefault();
        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(e.target),
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(data => { if(data.success) { loadMessages(); toggleReply(parentId); } })
        .catch(err => console.error('Fetch Error:', err));
    }

    function loadMessages() { fetch('/api/messages').then(r => r.json()).then(renderMessages); }
    loadMessages();
</script>
```

---

## 3. 開發檢查清單
1.  **HTML 表單**: `<form>` 必須包含 `enctype="multipart/form-data"` 屬性。
2.  **Storage**: 確保已執行 `php artisan storage:link`，以使 `/storage/` 路徑公開可讀。
3.  **CSRF**: 頁面需包含 `<meta name="csrf-token" content="{{ csrf_token() }}">`。


---

## 4. 留言刪除功能 (新功能)

### 4.1 Route (routes/web.php)
```php
Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
```

### 4.2 Controller (app/Http/Controllers/MessageController.php)
新增 `destroy` 方法：
```php
public function destroy(Message $message)
{
    // 確認是本人的留言才能刪除
    if ($message->user_id !== auth()->id()) {
        return response()->json([
            'success' => false,
            'message' => '您沒有權限刪除此訊息'
        ], 403);
    }

    // 同時刪除子留言（回覆）
    $message->replies()->delete();
    
    $message->delete();

    return response()->json(['success' => true]);
}
```

### 4.3 Model (app/Models/Message.php)
確認關聯存在：
```php
// 這是留言跟父留言的關聯
public function replies() 
{
    return $this->hasMany(Message::class, 'parent_id');
}

public function user()
{
    return $this->belongsTo(User::class);
}
```

### 4.4 前端 JS 刪除邏輯
```javascript
function deleteMessage(messageId) {
    if (!confirm('確定要刪除這則訊息嗎？')) return;
    
    fetch(`/messages/${messageId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                .getAttribute('content'),
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadMessages();
        } else {
            alert(data.message || '刪除失敗');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('發生錯誤，請稍後再試');
    });
}
```

### 4.5 Migration 資料表欄位確認
確認 `messages` table 具備 `user_id` 和 `parent_id`：
```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('parent_id')->nullable()->constrained('messages')->onDelete('cascade'); // 回覆用
    $table->text('content');
    $table->string('image_path')->nullable();
    $table->timestamps();
});
```



---

## 5.4 實作編輯按鈕與串接邏輯 (完整代碼手冊)

請將以下程式碼完整整合至 `resources/views/feed.blade.php` 的 `<script>` 標籤中。

### 步驟 1: 新增 editMessage 函式
此函式負責切換顯示模式，將內容區塊轉換為編輯框。請貼在 `deleteMessage` 函式下方：

```javascript
// 切換編輯模式 UI
function editMessage(messageId) {
    const messageDiv = document.querySelector(`#message-${messageId} p:nth-child(2)`);
    const originalContent = messageDiv.innerText;

    messageDiv.innerHTML = `
        <textarea id="edit-textarea-${messageId}" class="w-full text-sm border rounded-lg p-2">${originalContent}</textarea>
        <div class="flex gap-2 mt-2">
            <button onclick="saveEdit(${messageId})" class="text-xs bg-blue-500 text-white px-2 py-1 rounded">儲存</button>
            <button onclick="loadMessages()" class="text-xs bg-gray-300 text-black px-2 py-1 rounded">取消</button>
        </div>
    `;
}
```

### 步驟 2: 新增 saveEdit 函式
此函式處理與後端的數據更新 (PATCH)。請緊接在 `editMessage` 函式後方：

```javascript
// 呼叫後端執行更新 (PATCH)
function saveEdit(messageId) {
    const newContent = document.getElementById(`edit-textarea-${messageId}`).value;
    
    fetch(`/messages/${messageId}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ content: newContent })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadMessages(); // 儲存成功後重新載入列表
        } else {
            alert(data.message || '更新失敗');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('發生錯誤，請稍後再試');
    });
}
```

### 步驟 3: 確認 Controller 更新邏輯
確保 `app/Http/Controllers/MessageController.php` 已正確定義 `update` 方法（參見 5.2 節）。

### 步驟 4: 測試與除錯
1. 點擊「編輯」按鈕，確認是否顯示 `<textarea>`。
2. 修改內容後點擊「儲存」，查看瀏覽器開發者工具 (F12) 的 Network 分頁，確認 `PATCH /messages/{id}` 請求狀態為 200。
3. 若發生錯誤，請先檢查 CSRF Token 是否正確帶入 Header。



### 5.1 Route (routes/web.php)
```php
Route::patch('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
```

### 5.2 Controller (app/Http/Controllers/MessageController.php)
新增 `update` 方法：
```php
public function update(Request $request, Message $message)
{
    // 確認是本人的留言才能修改
    if ($message->user_id !== auth()->id()) {
        return response()->json([
            'success' => false,
            'message' => '您沒有權限修改此訊息'
        ], 403);
    }

    $validated = $request->validate([
        'content' => 'required|string|max:1000',
    ]);

    $message->update([
        'content' => $validated['content'],
    ]);

    return response()->json(['success' => true]);
}
```

### 5.3 前端 UI 與邏輯
1. **編輯模式開關**: 在留言卡片中新增「編輯」按鈕，點擊後將內容區塊替換為 `textarea`。
2. **提交與儲存**: 
   - 建立 `updateMessage(messageId, newContent)` 函式。
   - 使用 `fetch` 搭配 `PATCH` 方法傳送請求。
   - 請求需帶入 `X-CSRF-TOKEN`。
3. **完成後回渲染**: 成功更新後，透過 JavaScript 將 `textarea` 切換回內容顯示模式，並更新 DOM 內容。

