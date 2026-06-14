# LinkLuv 專案 AWS 部署指南

本文件提供將 LinkLuv Laravel 專案部署至 AWS 的詳細步驟，包含 Redis 快取設定與 S3 媒體儲存架構。

---

## 1. AWS 基礎架構準備

### 1.1 資料庫 (RDS)
1. 在 AWS 控制台建立 **RDS MySQL** 實例。
2. 建立資料庫，並取得 EndPoint、Username、Password。
3. 設定 Security Group，允許 EC2 的內網 IP 連接。

### 1.2 快取 (ElastiCache for Redis)
1. 建立 **ElastiCache Redis** 叢集。
2. 確保與 EC2 在同一個 VPC。
3. 取得 Redis EndPoint (例如: `xxxx.cache.amazonaws.com`)。

### 1.3 儲存 (S3)
1. 建立 S3 Bucket (例如: `linkluv-media`)。
2. 建立 IAM User，賦予 `AmazonS3FullAccess` 權限。
3. 取得 `AWS_ACCESS_KEY_ID` 與 `AWS_SECRET_ACCESS_KEY`。

### 1.4 計算 (EC2)
1. 啟動 Ubuntu EC2 實例。
2. 安裝 PHP 8.x、Nginx、Composer、MySQL 客戶端、Redis 擴展 (`php-redis`)。

---

## 2. Laravel 專案設定

### 2.1 安裝必要的 SDK
在專案根目錄執行：
```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
composer require predis/predis
```

### 2.2 設定環境變數 (`.env`)
修改伺服器上的 `.env`：
```env
# Database
DB_HOST=你的RDS_ENDPOINT
DB_DATABASE=linkluv
DB_USERNAME=admin
DB_PASSWORD=xxxx

# Cache (Redis)
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=你的REDIS_ENDPOINT
REDIS_PASSWORD=null
REDIS_PORT=6379

# Storage (S3)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=你的IAM_KEY
AWS_SECRET_ACCESS_KEY=你的IAM_SECRET
AWS_DEFAULT_REGION=ap-northeast-1
AWS_BUCKET=linkluv-media
AWS_USE_PATH_STYLE_ENDPOINT=false
```

---

## 3. 設定 Laravel 組態

### 3.1 Redis 快取
確保 `config/database.php` 中的 `redis` 設定正確。Laravel 預設會使用 `.env` 中的 REDIS_HOST。

### 3.2 S3 媒體儲存
確認 `config/filesystems.php` 中的 S3 設定。
若要讓影片與圖片存入 S3，請在程式碼中使用：
```php
Storage::disk('s3')->putFile('videos', $file);
```

---

## 4. 部署步驟

1. **上傳程式碼**：透過 Git 或 SCP 將程式碼部署到 `/var/www/linkluv`。
2. **權限設定**：
   ```bash
   sudo chown -R www-data:www-data /var/www/linkluv/storage
   sudo chmod -R 775 /var/www/linkluv/storage
   ```
3. **遷移資料庫**：
   ```bash
   php artisan migrate --force
   ```
4. **設定儲存連結** (若有部分公開檔案需連結)：
   ```bash
   php artisan storage:link
   ```
5. **快取配置** (優化效能)：
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## 5. 安全注意事項
*   **不要將 `.env` 提交到 Git**。
*   **Security Groups**：嚴格限制 EC2 的 SSH (22) 與 Nginx (80/443) 存取權限。
*   **IAM Policy**：S3 的權限應遵循最小權限原則 (Least Privilege)。
