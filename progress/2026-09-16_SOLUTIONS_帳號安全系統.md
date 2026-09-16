# 2026-09-16 帳號安全系統問題排查與解決方案詳解 (Issues & Solutions)

*   **對應進度主檔**：[`progress/PROGRESS.md`](./PROGRESS.md)
*   **對應工作標題**：【帳號安全系統重構、OAuth 防劫持與信箱驗證】
*   **記錄日期**：2026-09-16
*   **負責人 / 測試者**：Wei

---

## 📌 問題目錄索引

1.  [問題一：信箱重複提示依然顯示英文（The email has already been taken）](#問題一信箱重複提示依然顯示英文the-email-has-already-been-taken)
2.  [問題二：註冊完成後，在 AWS 測試卻「直接登入」未進入驗證攔截](#問題二註冊完成後在-aws-測試卻直接登入未進入驗證攔截)
3.  [問題三：Google 建立的新用戶登入後，被迫跳出信件認證（Verify Email）](#問題三google-建立的新用戶登入後被迫跳出信件認證verify-email)
4.  [問題四：OAuth 預先卡位劫持風險（Pre-Account Takeover）](#問題四oauth-預先卡位劫持風險pre-account-takeover)
5.  [問題五：未驗證幽靈帳號濫用「忘記密碼」發信騷擾第三人](#問題五未驗證幽靈帳號濫用忘記密碼發信騷擾第三人)
6.  [問題六：個人主頁 Google 帳號綁定時的「選錯帳號防呆」](#問題六個人主頁-google-帳號綁定時的選錯帳號防呆)

---

### 問題一：信箱重複提示依然顯示英文（The email has already been taken）

#### 1. 問題現象
在 `lang/zh_TW/validation.php` 加上了自訂中文訊息，但前端送出重複的 Email 時，畫面上依然噴出英文：  
`The email has already been taken.`

#### 2. 根本原因
根目錄 `.env` 檔案中第 12 行設定了 `APP_LOCALE=en`。  
Laravel 在執行表單驗證時，環境變數權限最高，強制指定系統載入 `lang/en/` 的公版英文檔案，完全略過 `lang/zh_TW/`。

#### 3. 解決代碼與邏輯
在控制器層級（Controller）強制指定專屬中文錯誤訊息，**無視系統當前是英文還是中文語系**：

*   **修改檔案**：[`app/Http/Controllers/Auth/RegisteredUserController.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Http/Controllers/Auth/RegisteredUserController.php)
```php
// ❌ 原本寫法（只傳入驗證規則，文字依賴語系檔）：
$request->validate([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
    'password' => ['required', 'confirmed', Rules\Password::defaults()],
]);

// ✅ 修正後寫法（加上第二個參數陣列，強制指定中文提示）：
$request->validate([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
    'password' => ['required', 'confirmed', Rules\Password::defaults()],
], [
    'email.unique' => '此信箱已被註冊使用。', // 👈 專屬中文直接生效
]);
```

---

### 問題二：註冊完成後，在 AWS 測試卻「直接登入」未進入驗證攔截

#### 1. 問題現象
使用者在 AWS 使用無痕視窗註冊新帳號，送出後沒有被擋在 `/verify-email` 頁面，而是直接登入進入動態牆。

#### 2. 根本原因
1.  **代碼未同步**：本地端完成了 commit 但尚未 `git push origin main`，AWS 上運行的依然是未修改的舊程式碼。
2.  **Model 介面漏宣告**：在 `User.php` 中雖然引入了 `use MustVerifyEmail`，但 Class 定義處遺漏了 `implements MustVerifyEmail`，導致 Laravel 的 `verified` 中介層判斷「該專案未開啟驗證」而直接全數放行。

#### 3. 解決代碼與邏輯
*   **修改檔案**：[`app/Models/User.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Models/User.php)
```php
// ❌ 原本寫法：
class User extends Authenticatable
{
    ...
}

// ✅ 修正後寫法（必須宣告 implements）：
class User extends Authenticatable implements MustVerifyEmail
{
    ...
}
```
*   **部署同步命令**：
```bash
# 本地推送
git push origin main

# AWS 拉取與清除快取
git pull origin main
php artisan config:clear
php artisan cache:clear
```

---

### 問題三：Google 建立的新用戶登入後，被迫跳出信件認證（Verify Email）

#### 1. 問題現象
使用者以未註冊過的 Google 帳號登入時，照理說 Google 帳號已具備信箱真實性，但登入後系統依然跳出「請驗證您的電子郵件（/verify-email）」攔截頁面。

#### 2. 根本原因
Laravel Eloquent 的 **批量賦值保護機制（Mass Assignment Protection）**。  
在 `User.php` 的 `$fillable` 白名單中，沒有加入 `'email_verified_at'`。  
因此當執行 `User::create(['email_verified_at' => now(), ...])` 時，Laravel 悄悄把這個欄位丟棄，寫入資料庫的仍是 `NULL`。中介層查到是 `NULL` 便判定為未驗證。

#### 3. 解決代碼與邏輯
*   **修改檔案**：[`app/Models/User.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Models/User.php)
```php
// ❌ 原本白名單（遺漏 email_verified_at）：
protected $fillable = ['name', 'email', 'password', 'profile_photo_path', 'google_id'];

// ✅ 修正後白名單（補上 email_verified_at）：
protected $fillable = ['name', 'email', 'password', 'profile_photo_path', 'google_id', 'email_verified_at'];
```
修正後，`User::create()` 寫入時 `email_verified_at` 會順利存入當前時間戳記，系統直接放行進入首頁。

---

### 問題四：OAuth 預先卡位劫持風險（Pre-Account Takeover）

#### 1. 問題現象
若採用一般教學常見的 `updateOrCreate`，只要 Google 回傳的信箱與資料庫相同就自動綁定登入。  
這會導致攻擊者若事先拿受害者的信箱註冊一般帳號密碼，等受害者未來用 Google 登入時自動合併，攻擊者仍能用一開始設定的密碼隨時登入受害者帳號偷窺資料。

#### 2. 根本原因
未針對「**既有一般帳號（`google_id` 為 NULL）**」做防禦性狀態分流。

#### 3. 解決代碼與邏輯
*   **修改檔案**：[`app/Http/Controllers/Auth/GoogleController.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Http/Controllers/Auth/GoogleController.php)
```php
$user = User::where('email', $googleUser->getEmail())->first();

// 🛑【狀況三攔截】：Email 已存在，但 google_id 為空（代表是一般密碼帳號，且未綁定）
if ($user && is_null($user->google_id)) {
    return redirect('/login')->withErrors([
        'email' => '此信箱已註冊一般帳號。請先使用密碼登入，再至個人資料頁面綁定 Google。'
    ]);
}

// ✅【狀況二放行】：已有 google_id，代表是本人綁定過的帳號
if ($user && !is_null($user->google_id)) {
    Auth::login($user);
    return redirect('/dashboard');
}

// 🆕【狀況一建立】：全新信箱，建立帳號並視為已驗證
$newUser = User::create([
    'name' => $googleUser->getName(),
    'email' => $googleUser->getEmail(),
    'google_id' => $googleUser->getId(),
    'password' => null,
    'email_verified_at' => now(),
]);
Auth::login($newUser);
return redirect('/dashboard');
```

---

### 問題五：未驗證幽靈帳號濫用「忘記密碼」發信騷擾第三人

#### 1. 問題現象
使用者若註冊時填寫別人或不存在的 Email，若直接前往 `/forgot-password` 輸入該信箱，系統會把重設密碼信寄送給無辜的信箱擁有者，造成垃圾郵件轟炸。

#### 2. 根本原因
Laravel Breeze 預設的 `PasswordResetLinkController@store` 只有驗證 Email 格式，沒有檢查該帳號「是否已完成信箱驗證」。

#### 3. 解決代碼與邏輯
*   **修改檔案**：[`app/Http/Controllers/Auth/PasswordResetLinkController.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Http/Controllers/Auth/PasswordResetLinkController.php)
```php
public function store(Request $request): RedirectResponse
{
    $request->validate([
        'email' => ['required', 'email'],
    ]);

    // 🔍【新增資安檢查】：檢查該帳號是否已完成信箱驗證
    $user = \App\Models\User::where('email', $request->email)->first();
    if ($user && ! $user->hasVerifiedEmail()) {
        return back()->withInput($request->only('email'))
            ->withErrors(['email' => '該信箱尚未完成驗證，無法使用忘記密碼功能。']);
    }

    $status = Password::sendResetLink($request->only('email'));
    ...
}
```

---

### 問題六：個人主頁 Google 帳號綁定時的「選錯帳號防呆」

#### 1. 問題現象
使用者登入帳號 A（`a@gmail.com`），進入個人主頁點擊「綁定 Google 帳號」，但在 Google 授權畫面中若不小心點選了帳號 B（`b@gmail.com`），容易造成錯位綁定。

#### 2. 解決代碼與邏輯
*   **修改檔案**：[`app/Http/Controllers/Auth/GoogleController.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/app/Http/Controllers/Auth/GoogleController.php)
```php
if (Auth::check()) {
    $currentUser = Auth::user();

    // 🔍【信箱一致性防呆比對】：授權信箱必須與當前登入帳號完全一致
    if ($googleUser->getEmail() !== $currentUser->email) {
        return redirect()->route('profile.edit')->withErrors([
            'google' => 'Google 信箱與目前登入帳號不一致，無法綁定！'
        ]);
    }

    $currentUser->update([
        'google_id' => $googleUser->getId(),
    ]);

    return redirect()->route('profile.edit')->with('status', 'google-linked');
}
```
*   **前端提示**：在 [`resources/views/profile/edit.blade.php`](file:///D:/G/My_projeckt/Luv/LinkLuv/resources/views/profile/edit.blade.php) 呈現錯誤與成功狀態卡片。
