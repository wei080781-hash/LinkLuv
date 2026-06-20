# LinkLuv 專案 AWS 詳細部署手冊

本文件將 AWS 部署過程拆解為最細緻的原子級步驟，請依照順序操作。

---

## 步驟 1：建立 AWS 雲端資源 (AWS 控制台)

### A. 建立 RDS (資料庫)
1.  **搜尋服務**：在 AWS 首頁搜尋欄輸入 `RDS` 並進入。
2.  **啟動建立**：點選「建立資料庫 (Create database)」。
3.  **選擇引擎**：選 `MySQL`，版本選 `8.0` 或以上。
4.  **選擇範本**：選「免費方案 (Free tier)」。
5.  **設定憑證**：
    *   資料庫名稱 (Database name)：`linkluv`
    *   主使用者名稱 (Master username)：`admin`
    *   密碼 (Master password)：設定一組強密碼並記下。
6.  **連線 (關鍵)**：
    *   選擇「計算資源」：選擇「不連接 EC2 計算資源」。
    *   公開存取權 (Public access)：選「否」。
    *   安全群組：選「建立新的」，命名為 `RDS-SG`。
7.  **確認**：點擊「建立資料庫」，等待狀態變為「可用 (Available)」。點進去資料庫詳情，記錄 **端點 (Endpoint)**。

### B. 建立 ElastiCache (Redis)
1.  **搜尋服務**：搜尋 `ElastiCache` 並進入。
2.  **建立叢集**：點選左側「Redis 叢集」，然後點選「建立」。
3.  **叢集設定**：
    *   選「叢集模式已停用」。
    *   節點類型：選 `cache.t3.micro`。
    *   複本：選 `0` (僅單一節點，最省錢)。
4.  **網路與安全性**：
    *   選預設 VPC。
    *   Port 保持預設 `6379`。
5.  **建立**：點選建立，等待狀態變為「可用」。記錄其 **主要端點 (Primary Endpoint)**。

### C. 建立 S3 (媒體儲存)
1.  **搜尋服務**：搜尋 `S3` 並進入。
2.  **建立儲存貯體**：點選「建立儲存貯體」。
    *   名稱：例如 `linkluv-media-bucket`。
    *   封鎖公開存取權：先暫時取消勾選（方便測試，若上線建議改為私有並用 CloudFront）。
3.  **建立 IAM 使用者**：
    *   搜尋 `IAM` -> `使用者` -> `建立使用者`。
    *   名稱：`linkluv-s3-user`。
    *   許可選項：選「直接附加政策」，搜尋並勾選 `AmazonS3FullAccess`。
    *   建立後，點擊使用者名稱 -> `安全憑證` -> `建立存取金鑰` -> 選「本地程式碼」-> 獲取 **Access Key ID** 和 **Secret Access Key** 並下載 CSV 儲存。

---

## 步驟 2：準備 EC2 伺服器

1.  **搜尋服務**：搜尋 `EC2` -> `啟動執行個體`。
2.  **名稱**：`LinkLuv-Server`。
3.  **鏡像 (AMI)**：選擇 `Ubuntu Server 22.04 LTS`。
4.  **執行個體類型**：選 `t3.medium`。
5.  **金鑰對**：建立一個新的 `.pem` 檔案並下載（這是你登入伺服器的鑰匙）。
6.  **網路設定 (安全群組)**：建立新安全群組 `EC2-SG`：
    *   SSH：Port 22，來源：你的 IP。
    *   HTTP：Port 80，來源：`0.0.0.0/0`。
    *   HTTPS：Port 443，來源：`0.0.0.0/0`。
7.  **啟動**：點擊啟動，記錄 **公有 IPv4 位址**。

---

## 步驟 3：在 EC2 上安裝環境
*透過 SSH 連線：`ssh -i 你的金鑰.pem ubuntu@你的公有IP`*

```bash
# 1. 更新系統
sudo apt update && sudo apt upgrade -y

# 2. 安裝 Nginx, PHP 8.2 (Laravel 11+ 建議版本)
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-redis php8.2-xml php8.2-mbstring php8.2-curl php8.2-gd php8.2-zip unzip git -y

# 3. 安裝 Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# 4. 準備資料夾
sudo mkdir -p /var/www/linkluv
sudo chown -R ubuntu:www-data /var/www/linkluv
```

---

## 步驟 4：設定 Laravel
1.  **部署程式碼**：(使用 git clone 或 scp 上傳你的專案)。
2.  **安裝套件**：
    ```bash
    cd /var/www/linkluv
    composer install --no-dev --optimize-autoloader
    ```
3.  **設定 .env**：
    ```bash
    cp .env.example .env
    nano .env
    ```
    *填入 RDS 與 Redis 的端點、帳號密碼，以及 S3 的 Key。*
4.  **初始化**：
    ```bash
    php artisan key:generate
    php artisan config:cache
    php artisan migrate --force
    ```
5.  **設定權限**：
    ```bash
    sudo chown -R www-data:www-data /var/www/linkluv/storage /var/www/linkluv/bootstrap/cache
    sudo chmod -R 775 /var/www/linkluv/storage /var/www/linkluv/bootstrap/cache
    ```

---

## 步驟 5：設定 Nginx

1.  **建立設定檔**：
    ```bash
    sudo nano /etc/nginx/sites-available/linkluv
    ```
    (填入之前提供的 Nginx 設定)
2.  **啟用並重啟**：
    ```bash
    sudo ln -s /etc/nginx/sites-available/linkluv /etc/nginx/sites-enabled/
    sudo rm /etc/nginx/sites-enabled/default
    sudo nginx -t
    sudo systemctl restart nginx
    ```
