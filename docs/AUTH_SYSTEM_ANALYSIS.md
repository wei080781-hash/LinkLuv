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
