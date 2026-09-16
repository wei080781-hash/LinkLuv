# LinkLuv 帳號系統安全架構、功能規格與問題排查手冊

本文件完整記錄 LinkLuv 專案在身分驗證（Authentication）、信箱驗證（Email Verification）、Google OAuth 第三方登入與帳號綁定系統的**架構設計、核心資安考量、曾遭遇問題及對應解決方案**。

---

## 1. 系統架構與核心功能模組

LinkLuv 帳號系統支援兩種主要身分驗證途徑，並透過 Laravel 原生驗證與安全中介層（Middleware）形成嚴密的防護網：

### 1.1 一般帳號註冊流程（Email + 密碼）
* **資料寫入**：使用者填寫姓名、信箱與密碼送出後，系統先執行格式與唯一性驗證（`unique:users`），驗證合格後立即寫入資料庫：
  * `google_id` 設為 `NULL`。
  * `email_verified_at` 設為 `NULL`（初始狀態為未驗證）。
* **信箱驗證（MustVerifyEmail）**：
  * 註冊完成後觸發 `Registered` 事件，自動透過 AWS Gmail SMTP 伺服器發送實體驗證信。
  * 使用者未點擊信件連結前，會被 `verified` 中介層攔截，無法瀏覽動態牆（`/feed`）等主要功能。
  * 點擊驗證連結後，更新 `email_verified_at = now()`，系統正式解除限制。

### 1.2 Google OAuth 第三方登入與防禦性決策
處理邏輯位於 `app/Http/Controllers/Auth/GoogleController.php` 的 `handleGoogleCallback()`：

| 狀況分類 | 資料庫查詢狀況 | 系統處置行為 | 資安與業務意義 |
| :--- | :--- | :--- | :--- |
| **狀況一** | Email 完全不存在 | 自動建立新帳號，寫入 `google_id`，`email_verified_at` 直接設為當前時間，`password = null`，直接登入。 | Google 已經驗證過其真實性，免去二次驗證麻煩。 |
| **狀況二** | Email 存在，且 `google_id` 已有值 | 驗證通過，直接登入。 | 正常第三方登入流程。 |
| **狀況三** | **Email 存在，但 `google_id` 為 NULL** | **嚴格攔截，禁止自動登入或自動合併！**<br>導回登入頁面並警告：*「此信箱已註冊一般帳號。請先使用密碼登入，再至個人資料頁面綁定 Google。」* | **防禦「OAuth 預先卡位帳號劫持（Pre-Account Takeover）」關鍵機制！** |

### 1.3 正統本人安全綁定（個人主頁）
* **路徑**：使用者必須先以一般密碼成功登入，證明為帳號本人，進入「個人主頁（`/profile`）」。
* **防呆比對**：點擊「綁定 Google 帳號」時，系統會比對 Google 回傳的信箱是否與當前登入使用者的信箱一致：
  * **一致**：將 `google_id` 寫入當前使用者，完成綁定。
  * **不一致**：立即擋下並提示 *「Google 信箱與目前登入帳號不一致，無法綁定！」*，防止綁錯或冒名帳號。

---

## 2. 核心資安痛點與解決架構

### 痛點一：OAuth 預先卡位劫持（Pre-Account Takeover）
* **威脅情境**：
  攻擊者得知受害者的 Gmail 地址，提前到 LinkLuv 註冊一般帳號並設定攻擊者自己的密碼。如果系統設計為「Google 登入時自動以 Email 合併帳號」，當受害者未來使用 Google 登入時，兩個帳號自動合併，攻擊者就能繼續用當初設定的密碼隨時登入受害者帳戶偷窺資料。
* **解決方案**：
  * 取消盲目自動合併（`updateOrCreate`）。
  * 實作**狀況三**：若偵測到一般帳號（`google_id is null`），一律不放行 Google 登入，強制要求先輸入原密碼登入，由本人在後台主動手動發起綁定。

### 痛點二：未驗證幽靈信箱濫用「忘記密碼」功能
* **威脅情境**：
  任何人註冊時若填入不屬於自己的信箱（或打錯的無辜者信箱），若未限制忘記密碼，惡意人士可隨時利用「忘記密碼」發送重設信去轟炸或騷擾第三方。
* **解決方案**：
  在 `PasswordResetLinkController@store` 增加檢查：
  ```php
  $user = \App\Models\User::where('email', $request->email)->first();
  if ($user && ! $user->hasVerifiedEmail()) {
      return back()->withInput($request->only('email'))
          ->withErrors(['email' => '該信箱尚未完成驗證，無法使用忘記密碼功能。']);
  }
  ```
  未通過 Email 實體驗證的帳號，絕對不發送重設信件。

### 痛點三：Google 建立的新使用者登入後被要求收驗證信
* **問題現象**：
  當使用者使用未註冊過的 Google 帳號登入（狀況一），照理說 Google 帳號已是真實信箱，但進去後卻被系統跳轉到「請驗證您的電子郵件」頁面。
* **原因分析**：
  在 `app/Models/User.php` 中，`$fillable`（可批量賦值白名單）遺漏了 `'email_verified_at'`。導致在執行 `User::create([... 'email_verified_at' => now()])` 時，Laravel 基於保護機制悄悄忽略了該欄位，寫入資料庫的仍是 `NULL`。
* **解決方案**：
  將 `email_verified_at` 加入 `User.php` 的 `$fillable` 白名單中：
  ```php
  protected $fillable = [
      'name', 'email', 'password', 'profile_photo_path', 'google_id', 'email_verified_at'
  ];
  ```

---

## 3. 開發與部署過程遭遇問題及排查記錄 (FAQ)

### Q1: 為什麼修改了語系檔繁體中文，畫面上還是顯示英文 `The email has already been taken.`？
* **根本原因**：
  雖然 `config/app.php` 預設寫 `zh_TW`，但專案根目錄的 `.env` 檔案中設定了 `APP_LOCALE=en`，環境變數優先級最高，導致系統一律去讀取 `lang/en/` 的英文訊息。
* **解決方式**：
  1. 在 `RegisteredUserController@store` 的 `$request->validate()` 第二參數直接自訂專屬中文訊息：
     ```php
     $request->validate([...], [
         'email.unique' => '此信箱已被註冊使用。',
     ]);
     ```
  2. 同時將 `.env` 中的 `APP_LOCALE=en` 修正為 `APP_LOCALE=zh_TW`。

---

### Q2: 為什麼在本地端測試修改完後，在 AWS 測試卻發現沒有作用（依然直接登入）？
* **根本原因**：
  本地端完成 `git commit` 後，尚未執行 `git push origin main` 推送至 GitHub，AWS 伺服器也尚未拉取（`git pull`），因此 AWS 伺服器實際上運行的是舊版程式碼。
* **解決方式**：
  1. 本地執行：`git push origin main`
  2. AWS 伺服器終端機執行：
     ```bash
     git pull origin main
     php artisan config:clear
     php artisan cache:clear
     ```

---

### Q3: 本地環境與 AWS 生產環境的寄信行為有何不同？
* **本地開發環境（Local）**：
  `.env` 設定為 `MAIL_MAILER=log`。
  信件不會真實發送，而是寫入 `storage/logs/laravel.log`。開發者可直接到該 Log 檔案末尾複製驗證網址進行測試。
* **AWS 生產環境（Production）**：
  `.env` 設定為 `MAIL_MAILER=smtp`，並配置 `smtp.gmail.com`（Port 465 / SSL）與 Gmail 應用程式密碼。
  系統會透過 Gmail 伺服器真實寄送驗證信與重設密碼信給使用者。

---

### Q4: 為什麼 Google 登入綁定成功後，每次點 Google 登入都會跳出「選擇帳號」畫面？
* **說明**：
  這屬於 Google OAuth 官方標準的多帳號防護機制（Account Chooser）。
  * 瀏覽器往往登入多組 Google 帳號，Google 必須確認使用者本次想要代表哪一位身分登入。
  * 此畫面**不需要輸入密碼**，只需滑鼠點擊頭像即可一鍵登入，不會重複要求個資授權同意。

---

## 4. 關鍵異動檔案與職責清單

| 檔案路徑 | 異動重點 | 核心職責 |
| :--- | :--- | :--- |
| [`app/Models/User.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Models/User.php) | 1. 實作 `MustVerifyEmail`<br>2. `$fillable` 加入 `email_verified_at` | 啟動原生信箱驗證介面，確保已驗證狀態能正確寫入資料庫。 |
| [`app/Http/Controllers/Auth/GoogleController.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Http/Controllers/Auth/GoogleController.php) | 1. 實作狀況一、二、三決策分支<br>2. 實作 `Auth::check()` 登入中帳號安全綁定 | 掌控第三方登入決策，防止預先卡位攻擊，並處理帳號綁定。 |
| [`app/Http/Controllers/Auth/PasswordResetLinkController.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Http/Controllers/Auth/PasswordResetLinkController.php) | 攔截 `! $user->hasVerifiedEmail()` | 阻止未通過驗證的幽靈帳號濫用密碼重設郵件。 |
| [`app/Http/Controllers/Auth/RegisteredUserController.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Http/Controllers/Auth/RegisteredUserController.php) | 自訂 `email.unique` 中文錯誤訊息 | 確保信箱重複時能提供清晰的繁體中文使用者提示。 |
| [`routes/web.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/routes/web.php) | 核心功能套用 `['auth', 'verified']` 中介層 | 未完成信箱驗證的使用者強制扣留在 `/verify-email`，禁止存取主要功能。 |
| [`resources/views/profile/edit.blade.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/resources/views/profile/edit.blade.php) | 插入「第三方帳號綁定」卡片區塊 | 呈現當前 Google 綁定狀態（已綁定/未綁定標籤）與綁定操作按鈕。 |
| [`resources/views/auth/register.blade.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/resources/views/auth/register.blade.php) | 移除「已經註冊了嗎？」超連結 | 精簡註冊頁面操作路徑，優化按鈕樣式。 |

---
*文件建立日期：2026-09-16*  
*系統版本：Laravel 11 + Breeze + Socialite (Google OAuth)*
