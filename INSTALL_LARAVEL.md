# Laravel 安裝說明書 (專案: LinkLuv)

這份文件記錄了在 D:\G\My_projeckt\LinkLuv 安裝 Laravel 的標準步驟。

## 1. 環境需求檢查
在開始安裝前，請確保系統已安裝以下軟體：
- **PHP** (版本 8.2 或以上)
- **Composer** (PHP 套件管理工具)
- **資料庫** (如 MySQL, MariaDB 或 SQLite)

## 2. 安裝步驟

由於目標目錄 `D:\G\My_projeckt\LinkLuv` 必須為空，建議採取以下其中一種方式：

### 方式 A：重新安裝 (推薦)
若該資料夾目前為空，最簡單的方法是刪除該資料夾，讓 Composer 自動重新建立它：

1. 開啟終端機 (PowerShell/CMD)
2. 進入父目錄：
   ```bash
   cd D:\G\My_projeckt
   ```
3. 刪除舊資料夾：
   ```bash
   rmdir /s /q LinkLuv
   ```
4. 安裝 Laravel：
   ```bash
   composer create-project laravel/laravel LinkLuv
   ```

### 方式 B：子目錄安裝
若不想刪除資料夾，可安裝在子目錄中：
1. 進入資料夾：
   ```bash
   cd D:\G\My_projeckt\LinkLuv
   ```
2. 安裝：
   ```bash
   composer create-project laravel/laravel src
   ```
   (專案檔案會位於 D:\G\My_projeckt\LinkLuv\src)

## 3. 啟動開發伺服器
安裝完成後，進入專案目錄並執行：

```bash
cd D:\G\My_projeckt\LinkLuv
php artisan serve
```

接著在瀏覽器打開：`http://127.0.0.1:8000`

## 4. 常見設定
- **.env 檔案**：安裝後會自動產生，請設定 `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` 以連接資料庫。
- **資料庫遷移**：設定完成後，執行以下指令建立資料表：
  ```bash
  php artisan migrate
  ```
