# 媒體資源快取與優化工作流程 (Media Caching Workflow)

本文件旨在引導您逐步建立 LinkLuv 專案的媒體資源（圖片/影片）快取與優化架構。本系統採用 **「上傳即處理 (Processing on Upload)」** 與 **「HTTP 快取控制 (HTTP Cache-Control)」** 機制。

---

## 總體資料流向 (Data Flow Overview)

1. **上傳階段**：Client -> Controller -> (影像處理) -> Storage (Local/S3)。
2. **存儲階段**：優化後的檔案存入 `storage/app/public`。
3. **讀取階段**：Client -> Web Server (Apache) -> Client (讀取快取)。

---

## 第一步：規劃儲存與存取架構

在開始寫程式前，我們必須先釐清檔案如何存放與存取。

### 步驟說明
在 Laravel 中，檔案存在 `storage/`，但 `public/` 資料夾是唯一可以被 Web Server 直接存取的路徑。

### 資料流向解析
*   **上傳時**：檔案從瀏覽器進入 PHP (Controller)，PHP 進行壓縮處理後，寫入 `storage/app/public/`。
*   **讀取時**：透過 `php artisan storage:link`，系統在 `public/` 目錄建立一個指向 `storage/app/public/` 的連結。瀏覽器存取 `http://localhost:8000/storage/...` 時，Web Server 會直接映射到實際檔案。

---

## 第二步：設定 Web 伺服器快取 (HTTP Level)

這是最重要的一步，我們不需要寫 Redis 快取二進位檔案，而是透過 HTTP 標頭讓瀏覽器快取。

### 步驟說明
編輯專案根目錄 `public/.htaccess` 檔案，設定瀏覽器對於媒體資源的快取過期時間。

### 程式碼與設定 (`public/.htaccess`)
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    # 快取圖片與影片一年
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType video/mp4 "access plus 1 year"
    ExpiresByType video/mov "access plus 1 year"
</IfModule>
```

### 資料流向解析
1. **瀏覽器請求檔案**。
2. **Apache Server** 回傳檔案時，附帶 `Cache-Control: max-age=31536000` 標頭。
3. **瀏覽器** 收到標頭，將檔案存入本地快取。
4. **下次存取**：瀏覽器直接從本機讀取，**完全不發送請求給您的伺服器**，實現極速讀取。

---

## 第三步：上傳時媒體優化 (Processing)

請參閱 `docs/影片上傳壓縮邏輯.md` 中的完整實作代碼，該文件包含 `MessageController` 的處理邏輯與 `CompressVideoJob` 的設定。

---

## 後續步驟
完成以上兩步後，您即建立了「自動化優化儲存」與「瀏覽器端極速快取」的雙層架構。接下來，我們將針對每一部分的細節進行實作。
