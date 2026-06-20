# 錯誤解決方案：Uncaught SyntaxError (Unexpected identifier 'console')

## 問題分析
該錯誤 `Uncaught SyntaxError: Unexpected identifier 'console'` 發生在瀏覽器嘗試將從 `fetch` 接收到的回應（Response）解析為 JSON 時。

這通常是因為你的 `MessageController@store` 方法在執行過程中發生了驗證錯誤（例如 `content` 為空或圖片驗證失敗），導致 Laravel 自動將使用者 **重導向 (Redirect)** 回原始頁面。因此，`fetch` 收到的不是 JSON 資料，而是**整個網頁的 HTML 原始碼**。

瀏覽器嘗試將這段 HTML 當作 JSON 解析，就會報出語法錯誤。

---

## 解決方案

### 1. 修改 MessageController.php (防止重導向)
在 `store` 方法中，確保當驗證失敗時，我們回傳的是 JSON 而不是導向。

**位置**: `app/Http/Controllers/MessageController.php`

```php
public function store(Request $request)
{
    // 修改驗證部分，若失敗直接回傳 JSON 錯誤
    $validator = \Validator::make($request->all(), [
        'content' => 'required|string|max:1000',
        'image' => 'nullable|image|max:2048',
        'parent_id' => 'nullable|exists:messages,id',
    ]);

    if ($validator->fails()) {
        return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
    }

    // ... 原本的儲存邏輯 ...
    $imagePath = $request->hasFile('image') ? $request->file('image')->store('messages', 'public') : null;
    
    \App\Models\Message::create([
        'content' => $request->content,
        'user_id' => auth()->id(),
        'parent_id' => $request->parent_id,
        'depth' => $depth,
        'image_path' => $imagePath,
    ]);

    return response()->json(['success' => true]);
}
```

### 2. 確認前端請求沒有被誤導向
檢查你的 `feed.blade.php` 中的 `<form>` 標籤，確保它沒有被自動觸發原生的表單提交（這會導致 URL 變成 `feed?_token=...`）。

**檢查位置**: `resources/views/components/message-form.blade.php`

確保 Form 的 `onsubmit` 寫法正確且有 `event.preventDefault()`：

```html
<form onsubmit="submitPost(event)" class="message-form" enctype="multipart/form-data">
    @csrf
    <!-- ... -->
</form>
```

並確認 JS 中的函式：
```javascript
function submitPost(event) {
    event.preventDefault(); // 這一行至關重要，防止頁面跳轉
    const formData = new FormData(event.target);
    // ... fetch 邏輯 ...
}
```

---

## 如何驗證是否解決？
1. 開啟瀏覽器 F12 -> Network (網路) 分頁。
2. 再次發送訊息。
3. 觀察 `messages` 的請求：
   * 如果 Status Code 是 `200` 且 Response 是 `{"success":true}`，代表問題解決。
   * 如果 Status Code 是 `422`，請查看 Response 中的 `errors` 欄位，檢查是否是你的驗證規則沒過。
   * 如果是 `302` 或 `419`，請檢查 `csrf-token` 是否在頁面 Load 後變更了。
