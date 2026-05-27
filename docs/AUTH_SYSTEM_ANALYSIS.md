# LinkLuv 登入與註冊功能分析

本專案使用 Laravel Breeze 作為身份驗證系統基礎。以下列出登入與註冊功能對應的頁面與後端邏輯關係。

## 1. 登入功能 (Login)

登入流程由 `AuthenticatedSessionController` 管理，視圖位於 `auth/login.blade.php`。

| 功能 | 視圖檔案 (Blade) | 控制器邏輯 (Controller) | 路由名稱 (Route Name) |
| :--- | :--- | :--- | :--- |
| **顯示登入頁面** | `resources/views/auth/login.blade.php` | `AuthenticatedSessionController@create` | `login` (GET) |
| **處理登入請求** | - | `AuthenticatedSessionController@store` | `login` (POST) |
| **登出** | - | `AuthenticatedSessionController@destroy` | `logout` (POST) |

### 核心邏輯流程：
1. 使用者存取 `/login`，觸發 `create` 方法，回傳登入表單視圖。
2. 使用者送出表單 (POST)，觸發 `store` 方法，控制器會驗證帳密，成功後建立 session 並跳轉。
3. 登出請求觸發 `destroy` 方法，清除 session 並重新導向。

---

## 2. 註冊功能 (Registration)

註冊流程由 `RegisteredUserController` 管理，視圖位於 `auth/register.blade.php`。

| 功能 | 視圖檔案 (Blade) | 控制器邏輯 (Controller) | 路由名稱 (Route Name) |
| :--- | :--- | :--- | :--- |
| **顯示註冊頁面** | `resources/views/auth/register.blade.php` | `RegisteredUserController@create` | `register` (GET) |
| **處理註冊請求** | - | `RegisteredUserController@store` | `register` (POST) |

### 核心邏輯流程：
1. 使用者存取 `/register`，觸發 `create` 方法，回傳註冊表單視圖。
2. 使用者送出表單 (POST)，觸發 `store` 方法，控制器執行資料驗證 (Validation)、建立使用者模型 (User Creation)，並自動進行登入與跳轉。

---

## 3. 其他相關功能 (密碼重設與驗證)

| 功能 | 視圖檔案 (Blade) | 控制器邏輯 (Controller) |
| :--- | :--- | :--- |
| **忘記密碼** | `auth/forgot-password.blade.php` | `PasswordResetLinkController` |
| **重設密碼** | `auth/reset-password.blade.php` | `NewPasswordController` |
| **Email 驗證提示** | `auth/verify-email.blade.php` | `VerifyEmailController` |
| **確認密碼** | `auth/confirm-password.blade.php` | `ConfirmablePasswordController` |

## 4. 帳號共用問題
發生「登入帳號共用」的問題，通常是因為 Session (工作階段) 的識別機制失效或衝突。

  以下是針對您專案環境的排查方向：

  1. 常見原因：環境變數衝突 (.env)
  Laravel 預設使用 APP_NAME 來產生 Session Cookie 名稱。如果多個開發者或多個環境共用相同的 APP_NAME 且運行在相同網域（例如都在 localhost 或共用 IP），瀏覽器會因為 Cookie
  名稱相同，導致使用者 B 的瀏覽器讀取到使用者 A 登入後的 Cookie，進而共享 Session。

  解決建議：
  請檢查您的 .env 檔案，確保 APP_NAME 是唯一的：

   1 APP_NAME="LinkLuv_Dev_YourName"
  修改後，請務必執行 php artisan config:clear。

  2. 資料庫 Session 驅動異常
  您的 config/session.php 設定為 driver => database。如果多個裝置或瀏覽器會話存入資料庫時，因為 sessions 資料表的狀態異常（例如 ID
  衝突或並未正確區隔），也可能導致系統誤認。

  排查步驟：
   * 請檢查 database/migrations/0001_01_01_000001_create_cache_table.php (或相關的 sessions 表) 是否正常。
   * 您可以嘗試暫時改用 file 驅動來測試是否仍有此現象：
       * 在 .env 中將 SESSION_DRIVER=file。
       * 清除快取 php artisan config:clear。
       * 如果 file 驅動下問題消失，代表是資料庫 Session 表的設計或清理機制有誤。

  3. 快取伺服器配置 (如果有的話)
  如果您的環境有使用 Redis 或 Memcached，且多個專案共用同一個 Redis 資料庫，也會發生 Session 資料覆蓋的問題。請檢查 config/database.php 中的 redis 設定，確保 prefix
  是唯一的。

  4. 程式碼邏輯檢視
  您提供的 AuthenticatedSessionController 與 LoginRequest 使用的是 Laravel Breeze 的標準做法：
   * $request->session()->regenerate();
   * Auth::attempt(...)

  這部分程式碼是安全的，問題極高機率出在 Session 設定層級 (環境變數) 或 伺服器層級 (共享 Session 儲存區)，而非您的登入邏輯程式碼。

  ---
  建議行動：
   1. 修改 .env 中的 APP_NAME。
   2. 執行 php artisan config:clear。
   3. 如果您是在本機開發，請嘗試清理瀏覽器的 Cookie 與 Cache，或者換一個瀏覽器進行測試，確認是否為 Cookie 污染。

   APP_NAME=Laravel                                                                                                                                                         
   APP_ENV=local                                                                                                                                                            
   APP_KEY=base64:RPckCWByStF8c58zsB9l6/eJWQcr7Lafz4gFjzVFNDU=                                                                                                              
   APP_DEBUG=true                                                                                                        APP_URL=http://localhost 

   

## 頭像沒有更新的原因
  buildNodeHTML 函式中，您目前硬編碼了 /user-avatar.jpg 作為頭像路徑：
    1 // 目前的問題程式碼
   2 <img src="${msg.user.avatar ?? '/user-avatar.jpg'}" ...>

  修改:
  請將 buildNodeHTML 中的 <img> 標籤修改為以下內容：

   1 // 修改後的程式碼
   2 <img src="${msg.user.profile_photo_url}" 
   3      style="width:${avatarSize}px; height:${avatarSize}px; border-radius:50%; flex-shrink:0; margin-right:8px; background:white; z-index:1;"
   4      onerror="this.src='/images/default-avatar.png'">

   為什麼這樣改？

   1. 使用模型屬性：我們改用了 msg.user.profile_photo_url。因為您的 User 模型中定義了 protected $appends = ['profile_photo_url']，Laravel 在回傳 message->user 的 JSON
      資料時，會自動包含這個網址欄位，所以前端直接讀取即可。
   2. 增加 onerror 保護 (防禦性程式設計)：雖然您的 User 模型已經有處理預設圖，但加上 onerror 是一個好習慣，萬一圖片網址真的失效（例如儲存空間設定錯誤），瀏覽器會自動切換到本機的預設圖片，而不會顯示破圖圖示。


