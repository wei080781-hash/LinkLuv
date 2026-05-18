# 討論串功能完整說明

本文件針對 LinkLuv 專案中的討論串（留言 + 回覆）功能進行完整整理，涵蓋資料模型、控制器、前端互動、階層留言邏輯與操作流程。

---

## 1. 功能概要

討論串功能包含：

- 發表留言
- 回覆特定留言
- 顯示互動式回覆視窗
- 支援階層式留言渲染（目前採 `depth` 與 `path` 實作）
- 以 `parent_id` 連結回覆訊息
- 以 `thread_id` 維持同一討論串關係

目前實作可支援「根留言」與其下的「回覆留言」階層結構，若要延伸至更多層，可繼續沿用相同 `depth` / `path` 計算方式。

---

## 2. 資料模型與欄位

`app/Models/Message.php` 中的核心欄位：

- `content`：留言內容
- `user_id`：留言作者
- `parent_id`：回覆目標留言 ID
- `thread_id`：討論串根留言 ID
- `depth`：階層深度（0 為根留言）
- `path`：物化路徑（materialized path），用於排序與階層
- `image_path`：上傳圖片路徑

模型關聯：

```php
class Message extends Model
{
    protected $fillable = ['content', 'user_id', 'parent_id', 'thread_id', 'depth', 'path', 'image_path'];

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }
}
```

---

## 3. API 與路由

`routes/web.php` 的相關路由：

```php
Route::middleware('auth')->group(function () {
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/api/messages', [MessageController::class, 'index']);
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    Route::patch('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::post('/messages/{message}/like', [MessageController::class, 'like'])->name('messages.like');
});
```

這些路由支援：發文、讀取留言列表、刪除、更新、點讚。

---

## 4. 存儲邏輯：`MessageController@store`

`app/Http/Controllers/MessageController.php` 的 `store` 邏輯如下：

1. 驗證輸入
2. 處理圖片上傳
3. 計算回覆深度 `depth`
4. 建立留言
5. 用 `parent.path` 與 `paddedId` 建立物化路徑 `path`
6. 更新 `thread_id` 與 `path`

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'content' => 'required|string|max:1000',
        'image' => 'nullable|image|max:2048',
        'parent_id' => 'nullable|exists:messages,id',
    ]);

    $parentId = $request->input('parent_id');
    $imagePath = $request->hasFile('image')
        ? $request->file('image')->store('messages', 'public')
        : null;

    $depth = 0;
    if ($parentId) {
        $parent = Message::findOrFail($parentId);
        $depth = $parent->depth + 1;
    }

    $message = Message::create([
        'user_id' => auth()->id(),
        'content' => $validated['content'],
        'parent_id' => $parentId,
        'image_path' => $imagePath,
        'depth' => $depth,
    ]);

    $paddedId = str_pad($message->id, 10, '0', STR_PAD_LEFT);
    $path = $parentId ? Message::findOrFail($parentId)->path . '.' . $paddedId : $paddedId;
    $threadId = $parentId ? Message::findOrFail($parentId)->thread_id : $message->id;

    $message->update([
        'path' => $path,
        'thread_id' => $threadId,
        'depth' => $depth,
    ]);

    return response()->json(['success' => true]);
}
```

### 重點說明

- 根留言：`parent_id` 為 `null`，`thread_id` 會自動等於自己的 `id`。
- 回覆留言：`parent_id` 指向父留言，`thread_id` 繼承父留言的 `thread_id`，確保同一討論串關聯。
- `path` 用於排序與分層顯示，方便前端依序渲染。

---

## 5. 讀取留言列表：`MessageController@index`

留言 API 會回傳帶有使用者、點讚計數與是否已按讚的結果：

```php
public function index()
{
    $userId = auth()->id();

    return Message::with(['user'])
        ->withCount('likes')
        ->withExists(['likes as is_liked' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }])
        ->orderBy('path', 'ASC')
        ->get();
}
```

排序使用 `path`，可讓留言與其回覆依照階層順序排列。

---

## 6. 前端互動式回覆視窗

`resources/views/feed.blade.php` 中，每則留言都包含：

- `回覆` 按鈕
- 隱藏的回覆表單區塊
- `toggleReply(id)` 在點擊時切換表單顯示/隱藏

```javascript
window.toggleReply = function(id) {
    const el = document.getElementById(`reply-form-${id}`);
    if (el) {
        el.classList.toggle('hidden');
    }
};
```

回覆表單本體：

```html
<div id="reply-form-${msg.id}" class="hidden mt-2" style="padding-left:${paddingLeft}px;">
    <form onsubmit="submitReply(event, ${msg.id})" class="flex gap-2">
        <input type="hidden" name="parent_id" value="${msg.id}">
        <input type="text" name="content" required placeholder="回覆..."
               class="rounded-full text-sm px-4 py-1.5 border w-full">
        <button type="submit" class="text-pink-600 text-sm font-bold px-3">送出</button>
    </form>
</div>
```

如上所示，回覆時會將 `parent_id` 包含在表單內，以便後端記錄回覆目標。

---

## 7. 前端留言渲染與階層結構

目前前端採用 `renderMessages` 及 `buildNodeHTML` 遞迴渲染：

```javascript
function renderMessages(messages) {
    const list = document.getElementById('messages-list');
    list.innerHTML = '';

    const map = new Map();
    messages.forEach(msg => map.set(msg.id, { ...msg, children: [] }));

    const roots = [];
    map.forEach(msg => {
        if (!msg.parent_id) {
            roots.push(msg);
        } else {
            const parent = map.get(msg.parent_id);
            if (parent) parent.children.push(msg);
        }
    });

    roots.forEach(root => {
        list.insertAdjacentHTML('beforeend', buildNodeHTML(root, 0));
    });
}

function buildNodeHTML(msg, depth) {
    const avatarSize = depth === 0 ? 40 : 32;
    const paddingLeft = avatarSize + 12;
    const imageHtml = msg.image_path
        ? `<div class="mt-2"><img src="/storage/${msg.image_path}" class="max-w-xs rounded-xl shadow-sm border border-gray-100"></div>`
        : '';

    const childrenHTML = msg.children.length > 0
        ? `<div style="margin-left:${depth < 2 ? '40px' : '0'}; margin-top:4px;">` +
          msg.children.map(child => buildNodeHTML(child, depth + 1)).join('') +
          `</div>`
        : '';

    const connector = depth > 0
        ? `<div style="position:absolute; left:-40px; top:${avatarSize / 2}px; width:40px; height:2px; background:#d1d5db;"></div>`
        : '';

    return `
    <div id="message-${msg.id}" class="mb-3" style="position:relative;">
        <div style="display:flex; align-items:flex-start; position:relative;">
            ${connector}
            <img src="${msg.user.avatar ?? '/user-avatar.jpg'}"
                 style="width:${avatarSize}px; height:${avatarSize}px; border-radius:50%; flex-shrink:0; margin-right:8px; background:white; z-index:1;">
            <div class="p-2 bg-gray-100 rounded-2xl shadow-sm" style="flex:1;">
                <p class="text-xs font-semibold text-gray-700">${msg.user.name}</p>
                <p class="text-sm text-gray-800">${msg.content}</p>
                ${imageHtml}
            </div>
        </div>
        <div class="mt-1 flex gap-3 text-xs text-gray-400" style="padding-left:${paddingLeft}px;">
            <button onclick="toggleReply(${msg.id})" class="hover:text-blue-600">回覆</button>
            <button onclick="deleteMessage(${msg.id})" class="hover:text-red-500">刪除</button>
            <button onclick="editMessage(${msg.id})" class="hover:text-blue-600">編輯</button>
            <button onclick="toggleLike(${msg.id})" class="flex items-center gap-1 ${msg.is_liked ? 'text-pink-500' : ''}">
                ❤️ ${msg.likes_count || 0}
            </button>
        </div>
        <div id="reply-form-${msg.id}" class="hidden mt-2" style="padding-left:${paddingLeft}px;">
            <form onsubmit="submitReply(event, ${msg.id})" class="flex gap-2">
                <input type="hidden" name="parent_id" value="${msg.id}">
                <input type="text" name="content" required placeholder="回覆..."
                       class="rounded-full text-sm px-4 py-1.5 border w-full">
                <button type="submit" class="text-pink-600 text-sm font-bold px-3">送出</button>
            </form>
        </div>
        ${childrenHTML}
    </div>
    `;
}
```

### 重點

- `renderMessages` 先將所有留言轉成 ID 映射，並建立 `children` 清單。
- `roots` 只包含 `parent_id == null` 的根留言。
- `buildNodeHTML` 用遞迴方式渲染留言與回覆。
- `depth` 用於控制縮排與樣式，使回覆能夠與根留言保持階層關係。

---

## 8. 傳送留言與回覆的前端邏輯

### 發表新留言

```javascript
window.submitPost = function(e) {
    e.preventDefault();
    fetch("{{ route('messages.store') }}", {
        method: 'POST',
        body: new FormData(e.target),
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
    .then(r => r.json())
    .then(d => { if(d.success) { loadMessages(); e.target.reset(); } });
};
```

### 回覆留言

```javascript
window.submitReply = function(e, parentId) {
    e.preventDefault();
    fetch("{{ route('messages.store') }}", {
        method: 'POST',
        body: new FormData(e.target),
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
    .then(r => r.json())
    .then(() => {
        loadMessages();
        toggleReply(parentId);
    });
};
```

### 注意事項

- 回覆與發文都使用 `FormData`，因此不要手動設置 `Content-Type`。
- 若要上傳圖片，前端表單必須包含 `enctype="multipart/form-data"`。
- CSRF Token 必須正確設置在 `<meta name="csrf-token">` 與 `fetch` header 中。

---

## 9. 刪除與編輯功能

### 刪除留言

```php
public function destroy(Message $message)
{
    if ($message->user_id !== auth()->id()) {
        return response()->json(['success' => false, 'message' => '無權刪除'], 403);
    }
    $message->replies()->delete();
    $message->delete();
    return response()->json(['success' => true]);
}
```

### 編輯留言

```php
public function update(Request $request, Message $message)
{
    if ($message->user_id !== auth()->id()) {
        return response()->json(['success' => false, 'message' => '您沒有權限修改此訊息'], 403);
    }
    $validated = $request->validate(['content' => 'required|string|max:1000']);
    $message->update(['content' => $validated['content']]);
    return response()->json(['success' => true]);
}
```

前端可將編輯按鈕換成 `textarea`，然後呼叫 `saveEdit(messageId)` 送出更新後刷新列表。

---

## 10. 兩層式階層留言說明

目前系統主要是「留言 + 回覆」結構：

- `depth == 0`：根留言
- `depth == 1`：直接回覆根留言
- 若回覆回覆，`depth` 仍會繼續累加，可支援更深層次

若你要強制限制為兩層顯示，可以在 `buildNodeHTML()` 中加入 `if (depth > 1) return '';` 或只渲染到第二層；但目前的資料結構與渲染方式本身已支持多層。

---

## 11. 建議改進

- 若希望更精準管理討論串，可在 `store` 中改用 `thread_id = $parent->thread_id ?? $message->id` 先計算後再更新，避免重複查 `Message::findOrFail($parentId)`。
- 回覆表單若要避免與留言重疊，可額外在 `toggleReply()` 中關閉其他已開啟的回覆表單。
- 若要顯示回覆對象名稱，可在 `reply` 按鈕旁顯示 `@${msg.user.name}`。

---

## 12. 最佳實作流程

1. 使用者按「回覆」按鈕。
2. `toggleReply` 顯示該留言下的回覆表單。
3. 使用者輸入內容並送出。
4. 前端送出 `parent_id` 與 `content` 至 `messages.store`。
5. 後端計算 `depth`、`thread_id`、`path`，建立留言。
6. 前端呼叫 `loadMessages()` 重新取得最新留言列表。
7. `renderMessages` 依 `parent_id` 將留言組成樹狀，並渲染出回覆階層。

---

## 13. 小結

這份實作將討論串功能拆分為：

- 資料模型 + 關聯
- 後端存儲與階層計算
- 前端動態回覆視窗
- 遞迴渲染留言樹
- 互動按鈕（回覆、刪除、編輯、點讚）

若你想要更進一步展現「互動式回覆視窗」，可以再改造 `toggleReply()` 讓同一時間只顯示一個回覆表單，並在回覆按鈕旁加上提示文字。
