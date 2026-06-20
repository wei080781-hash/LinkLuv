# LinkLuv 留言系統開發規範

本文件定義 LinkLuv 留言系統的架構標準、UI 設計模式及核心維護流程。

## 1. 架構標準
*   **資料模型**: 採用 `messages` 資料表。
    *   核心欄位: `user_id`, `parent_id`, `thread_id`, `image_path`。
    *   排序標準: `ORDER BY COALESCE(thread_id, id) ASC, created_at ASC`。
*   **後端 API**: `MessageController` 必須回傳標準 JSON 格式，並在 `index` 中預加載 `user` 關係及 `likes_count`。

## 2. UI 設計模式：Facebook 風格 (氣泡框 + 階層連線)
為了確保留言層級的可讀性，UI 採用「氣泡框 + 樹狀連線」設計，禁止單純使用 `margin-left` 進行無限深度的扁平縮排。

### 2.1 前端實作規範
*   **渲染架構**: 使用遞迴式 `buildThread` 邏輯。
*   **深度計算**: 必須根據 `msg.depth` (需在 Controller 計算) 或前端依據 `parent_id` 追溯計算得出。
*   **線條樣式**: 
    *   使用 `has-connector` 類別 (定義於 `app.css`) 繪製連線。
    *   根留言 (Depth 0) 必須帶有 `no-line` 類別以隱藏連線。
*   **響應式細節**: 隨著層級越深，頭像 (`avatarSize`) 應適度縮小，橫線長度 (`hLineWidth`) 應適度增加，防止內容區塊溢出。

## 3. 維護檢查清單
*   **資料庫一致性**: 任何 `store` 操作後，必須確保 `thread_id` 已正確指派。
*   **編譯檢查**: 修改 `app.css` 後，必須確認沒有產生 `postcss` 編譯錯誤（請注意 CSS 語法正確性，避免引入行號或無效 `@tailwind` 指令）。
*   **軟刪除**: 修改留言 Model 時，必須同步檢查 `SoftDeletes` 關聯。

## 4. 變更記錄方針
*   若要新增層級展示邏輯，請優先修改 `renderMessages` 的 `buildReplyHTML` 函式。
*   嚴禁在 Blade 視圖中直接編寫過於複雜的 SQL 邏輯。
