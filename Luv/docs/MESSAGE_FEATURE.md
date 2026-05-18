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

## 2. 圖片上傳功能實現 (New Feature)

### 2.1 後端邏輯 (MessageController.php)
在 `store` 方法中，使用 `Request` 的 `hasFile` 與 `store` 方法處理上傳。

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'content' => 'required|string|max:1000',
        'image' => 'nullable|image|max:2048', // 限制 2MB
        'parent_id' => 'nullable|exists:messages,id',
    ]);

    // 將圖片儲存至 storage/app/public/messages
    $imagePath = $request->hasFile('image') 
        ? $request->file('image')->store('messages', 'public') 
        : null;

    Message::create([
        'content' => $request->content,
        'user_id' => auth()->id(),
        'parent_id' => $request->input('parent_id'),
        'image_path' => $imagePath,
        // ... 其他欄位
    ]);

    return response()->json(['success' => true]);
}
```

### 2.2 前端發送邏輯
前端必須使用 `FormData` 進行 `fetch` 傳送，且**不可**指定 `Content-Type`（瀏覽器會自動設為 `multipart/form-data` 並包含正確的 boundary）。

```javascript
const formData = new FormData(formElement);
fetch("{{ route('messages.store') }}", {
    method: 'POST',
    body: formData,
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
});
```

### 2.3 圖片渲染
在 JavaScript 的 `renderMessages` 函式中，透過動態字串拼接處理圖片路徑，以確保瀏覽器正確請求儲存的檔案：

```javascript
const imageHtml = msg.image_path ? `
    <div class="mt-3">
        <img src="/storage/${msg.image_path}" class="max-w-xs rounded-xl shadow-sm border border-gray-100">
    </div>
` : '';
```
*(註：此路徑假設專案部署於網域根目錄，且已執行 `php artisan storage:link` 建立軟連結)*

---

## 3. 前端渲染與提交邏輯 (feed.blade.php)
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

## 4. 開發檢查清單
1.  **HTML 表單**: `<form>` 必須包含 `enctype="multipart/form-data"` 屬性。
2.  **Storage**: 確保已執行 `php artisan storage:link`，以使 `/storage/` 路徑公開可讀。
3.  **CSRF**: 頁面需包含 `<meta name="csrf-token" content="{{ csrf_token() }}">`。

---

## 5. 留言刪除功能 (新功能)

### 5.1 Route (routes/web.php)
```php
Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
```

### 5.2 Controller (app/Http/Controllers/MessageController.php)
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

### 5.3 Model (app/Models/Message.php)
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

### 5.4 前端 JS 刪除邏輯
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

---

## 6. 留言編輯功能 (新功能)

### 6.1 Route (routes/web.php)
```php
Route::patch('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
```

### 6.2 Controller (app/Http/Controllers/MessageController.php)
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

---

## 7. 留言階層與排序：物化路徑 (Materialized Path) 實作方案

為了完美解決留言的階層縮排與排序問題（確保子留言永遠緊跟在父留言下方），我們採用「物化路徑」方案，這是一種高效且業界標準的做法。

### 7.1 資料庫結構設計
我們透過 `path` 來記錄層級關係，並透過 `depth` 欄位直接提供前端渲染縮排資訊。

#### 步驟 1: 新增欄位 (Migration)
執行 `php artisan make:migration add_path_and_depth_to_messages_table --table=messages` 並加入以下欄位：

```php
public function up(): void
{
    Schema::table('messages', function (Blueprint $table) {
        $table->string('path', 1024)->nullable()->index()->after('parent_id');
        $table->unsignedTinyInteger('depth')->default(0)->after('path');
    });
}
```

#### 步驟 2: 更新模型 (Model)
在 `app/Models/Message.php` 中加入新欄位至 `$fillable`：

```php
protected $fillable = [
    'content', 'user_id', 'parent_id', 'thread_id', 'depth', 'path', 'image_path'
];
```

---

### 7.2 後端實作邏輯

#### 1. 修改 `store` 方法 (MessageController.php)
在留言建立後，計算路徑與深度並更新：

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'content' => 'required|string|max:1000',
        'parent_id' => 'nullable|exists:messages,id',
    ]);

    $parentId = $request->input('parent_id');
    
    // 1. 先建立留言以取得 ID
    $message = Message::create([
        'user_id' => auth()->id(),
        'content' => $request->content,
        'parent_id' => $parentId,
        'image_path' => $request->hasFile('image') ? $request->file('image')->store('messages', 'public') : null,
    ]);

    // 2. 計算路徑 (Padding ID 以確保字串排序正確)
    $paddedId = str_pad($message->id, 10, '0', STR_PAD_LEFT);
    $depth = 0;
    
    $parent = $parentId ? Message::findOrFail($parentId) : null;
    
    $path = $parent ? $parent->path . '.' . $paddedId : $paddedId;
    $depth = $parent ? $parent->depth + 1 : 0;
    $threadId = $parent ? $parent->thread_id : $message->id;

    // 3. 更新路徑
    $message->update(['path' => $path, 'depth' => $depth, 'thread_id' => $threadId]);

    return response()->json(['success' => true]);
}
```

#### 2. 修改 `index` 方法
直接依據 `path` 進行排序，即可得到完美的樹狀順序：

```php
public function index()
{
    return Message::with(['user'])->orderBy('path', 'ASC')->get();
}
```

---

## 8. 人物氣泡框與連線設計 (Facebook 風格)

本節紀錄了 Facebook 風格留言系統的「氣泡框與連線 (Connector)」完整渲染邏輯。

### 8.1 核心渲染架構 (JavaScript)
我們採用「分組 (Thread)」架構，確保連線能準確從根留言延伸至回覆留言。

#### A. 主渲染函式
此函式將後端返回的扁平數據轉為 Thread 分組，並呼叫渲染組件。

```javascript
function renderMessages(messages) {
    const list = document.getElementById('messages-list');
    list.innerHTML = '';
    
    const threads = [];
    const threadMap = new Map(); // 關鍵：用 Map 來存 Root 與其對應的 Thread 物件

    messages.forEach(msg => {
        if (msg.depth === 0) {
            // 它是根留言
            const thread = { root: msg, replies: [] };
            threads.push(thread);
            threadMap.set(msg.id, thread); // 將 ID 記錄下來
        } else {
            // 它是回覆，需要找到它屬於哪個根留言
            const rootThread = threadMap.get(msg.thread_id);
            if (rootThread) {
                rootThread.replies.push(msg);
            } 
        }
    });

    threads.forEach(thread => {
        list.insertAdjacentHTML('beforeend', buildThread(thread.root, thread.replies));
    });
}
```

#### B. 遞迴組件渲染 (繪製連線)
透過 `buildThread` 與 `buildReplyHTML` 繪製連線。

```javascript
// 建立一整串留言
function buildThread(root, replies) {
    const hasReplies = replies.length > 0;
    const vLine = hasReplies ? `<div style="width:2px; flex:1; background:#d1d5db; border-radius:1px; margin-top:3px;"></div>` : '';

    const repliesHTML = replies.map((reply, index) => {
        const isLast = index === replies.length - 1;
        return buildReplyHTML(reply, isLast);
    }).join('');

    return `
    <div class="flex mb-6">
        <div style="display:flex; flex-direction:column; align-items:center; flex-shrink:0; width:10px;">
            <div style="width:9px; height:9px; margin-top:14px; border-radius:50%; border:2px solid #d1d5db; background:#fff; flex-shrink:0;"></div>
            ${vLine}
        </div>
        <div style="flex:1; padding-left:10px;">
            ${buildRootHTML(root)}
            ${repliesHTML}
        </div>
    </div>
    `;
}

// 根留言渲染
function buildRootHTML(msg) {
    const imageHtml = msg.image_path ? `
        <div class="mt-3">
            <img src="/storage/${msg.image_path}" class="max-w-xs rounded-xl shadow-sm border border-gray-100">
        </div>
    ` : '';

    return `
    <div id="message-${msg.id}" class="mb-4">
        <div class="flex items-start gap-3">
            <img src="${msg.user.avatar ?? '/user-avatar.jpg'}" class="w-10 h-10 rounded-full flex-shrink-0">
            <div class="p-3 bg-gray-100 rounded-2xl shadow-sm">
                <p class="text-xs font-semibold text-gray-700 mb-1">${msg.user.name}</p>
                <p class="text-sm text-gray-800">${msg.content}</p>
                ${imageHtml}
            </div>
        </div>
        <div class="mt-1 flex gap-3 text-xs text-gray-400" style="padding-left:52px;">
            <button onclick="toggleReply(${msg.id})" class="hover:text-blue-600">回覆</button>
            <button onclick="deleteMessage(${msg.id})" class="hover:text-red-500">刪除</button>
            <button onclick="editMessage(${msg.id})" class="hover:text-blue-600">編輯</button>
            <button onclick="toggleLike(${msg.id})" class="flex items-center gap-1 ${msg.is_liked ? 'text-pink-500' : ''}">❤️ ${msg.likes_count || 0}</button>
        </div>
        <div id="reply-form-${msg.id}" class="hidden mt-2" style="padding-left:52px;">
            <form onsubmit="submitReply(event, ${msg.id})" class="flex gap-2">
                <input type="hidden" name="parent_id" value="${msg.id}">
                <input type="text" name="content" required placeholder="回覆..." class="rounded-full text-sm px-4 py-1.5 border w-full">
                <button type="submit" class="text-pink-600 text-sm font-bold px-3">送出</button>
            </form>
        </div>
    </div>
    `;
}

// 回覆留言渲染 (含連線計算)
function buildReplyHTML(msg, isLast) {
    const depth = msg.depth || 1;
    const hLineWidth = 16 + (depth - 1) * 28;
    const avatarSize = Math.max(24, 30 - (depth - 1) * 3);
    const imageHtml = msg.image_path ? `<div class="mt-2"><img src="/storage/${msg.image_path}" class="max-w-xs rounded-xl shadow-sm border border-gray-100"></div>` : '';

    return `
    <div id="message-${msg.id}" style="display:flex; align-items:flex-start; margin-bottom:${isLast ? '4px' : '10px'}; margin-left:-16px;">
        <div style="display:flex; align-items:center; flex-shrink:0; padding-top:10px;">
            <div style="width:9px; height:9px; border-radius:50%; border:2px solid #d1d5db; background:#fff; flex-shrink:0;"></div>
            <div style="width:${hLineWidth}px; height:2px; background:#d1d5db;"></div>
        </div>
        <div style="flex:1;">
            <div style="display:flex; align-items:flex-start; gap:8px;">
                <img src="${msg.user.avatar ?? '/user-avatar.jpg'}" style="width:${avatarSize}px; height:${avatarSize}px; border-radius:50%; flex-shrink:0;">
                <div class="p-2 bg-gray-100 rounded-2xl shadow-sm" style="flex:1;">
                    <p class="text-xs font-semibold text-gray-700 mb-1">${msg.user.name}</p>
                    <p class="text-sm text-gray-800">${msg.content}</p>
                    ${imageHtml}
                </div>
            </div>
            <div style="padding-left:${avatarSize + 8}px;" class="mt-1 flex gap-3 text-xs text-gray-400">
                <button onclick="toggleReply(${msg.id})" class="hover:text-blue-600">回覆</button>
                <button onclick="deleteMessage(${msg.id})" class="hover:text-red-500">刪除</button>
                <button onclick="editMessage(${msg.id})" class="hover:text-blue-600">編輯</button>
            </div>
        </div>
    </div>
    `;
}
```

---

## 6. 軟刪除 (Soft Deletes) 欄位缺失問題
參見原始專案紀錄。
