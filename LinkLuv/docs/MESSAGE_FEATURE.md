# 留言功能實現紀錄

## 功能描述
在 `feed.blade.php` 頁面新增一個簡單的留言發布與列表顯示區塊。此區塊不包含任何 CSS 樣式（僅基礎 HTML 原生呈現）。

## 檔案結構與關聯
1.  **資料模型**: `app/Models/Message.php`
    - 用於定義留言資料的 `fillable` 欄位（content, user_id）。
2.  **控制器**: `app/Http/Controllers/MessageController.php`
    - `store` 方法: 接收表單數據並寫入資料庫。
3.  **路由定義**: `routes/web.php`
    - `GET /feed`: 更新以同時查詢所有留言並傳遞至視圖。
    - `POST /messages`: 指向 MessageController@store。
4.  **視圖呈現**: `resources/views/feed.blade.php`
    - 包含 `<form>` 表單與 `@foreach` 留言顯示迴圈。

## 資料流程
1. 使用者在 `feed` 頁面輸入內容並點擊「送出」。
2. `form` 發送 POST 請求至 `/messages`。
3. `MessageController` 驗證請求並儲存至資料庫。
4. 頁面重定向回 `back()`，自動刷新顯示最新的留言。
