# LinkLuv 身分驗證與帳戶管理系統

## 概述

此系統包含完整的 Laravel 原生認證流程：
- 註冊
- 登入
- 登出
- Email 驗證
- 忘記密碼與重設密碼
- 個人資訊編輯
- 密碼更新
- 帳戶刪除

此功能由 `routes/auth.php` 與 `app/Http/Controllers/Auth/*` 共同實作，前端頁面使用 `resources/views/auth/*` 與 `resources/views/profile/*`。

---

## 1. 路由定義

檔案：`routes/auth.php`

```php
<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
```

### 邏輯說明

- `guest` 群組：只能未登入使用者訪問的頁面。
- `auth` 群組：必須登入後才能訪問的帳戶功能。
- 這個路由組合正好對應登入、註冊、驗證、重設密碼與登出。

---

## 2. 註冊流程

### 2.1 控制器：`app/Http/Controllers/Auth/RegisteredUserController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
```

### 2.2 表單頁面：`resources/views/auth/register.blade.php`

責任：顯示註冊表單並提交到 `POST /register`。

主要欄位：
- `name`
- `email`
- `password`
- `password_confirmation`

此頁面使用 Blade 元件 `x-input-label`、`x-text-input`、`x-input-error`。

### 2.3 註冊邏輯

1. 使用者提交註冊資料。
2. 後端驗證欄位格式與唯一性。
3. 密碼雜湊後寫入 `users` 表。
4. 觸發 `Registered` 事件，可能發送驗證信。
5. 自動登入並導向 `dashboard`（目前重定向到 `feed`）。

---

## 3. 登入流程

### 3.1 控制器：`app/Http/Controllers/Auth/AuthenticatedSessionController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
```

### 3.2 驗證請求：`app/Http/Requests/Auth/LoginRequest.php`

主要邏輯：
- `rules()`：驗證 `email` 與 `password`。
- `authenticate()`：呼叫 `Auth::attempt()`，並使用 `remember`。
- 連續失敗超過 5 次時啟用速率限制。

### 3.3 登入頁面：`resources/views/auth/login.blade.php`

責任：
- 顯示 email / password 表單
- 允許勾選「記住我」
- 提供忘記密碼連結

### 3.4 登入流程

1. 使用者提交登入表單。
2. `LoginRequest` 驗證欄位。
3. `Auth::attempt()` 嘗試登入。
4. 成功後重置 Session 令牌。
5. 導向原本意圖頁面或 `dashboard`。

---

## 4. 忘記密碼與重設密碼

### 4.1 發送重設連結

控制器：`app/Http/Controllers/Auth/PasswordResetLinkController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
```

頁面：`resources/views/auth/forgot-password.blade.php`

責任：
- 讓使用者輸入註冊信箱
- 提交到 `POST /forgot-password`
- 顯示操作結果狀態

### 4.2 重設密碼頁面

控制器：`app/Http/Controllers/Auth/NewPasswordController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
```

頁面：`resources/views/auth/reset-password.blade.php`

責任：
- 接收 `token` 與 email
- 輸入新密碼與確認密碼
- 提交到 `POST /reset-password`

---

## 5. Email 驗證

### 5.1 驗證提示頁面

控制器：`app/Http/Controllers/Auth/EmailVerificationPromptController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route('dashboard', absolute: false))
                    : view('auth.verify-email');
    }
}
```

頁面：`resources/views/auth/verify-email.blade.php`

責任：
- 提示使用者檢查信箱
- 提供重送驗證信按鈕
- 提供登出連結

### 5.2 驗證連結處理

控制器：`app/Http/Controllers/Auth/VerifyEmailController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
```

### 5.3 邏輯說明

- 當使用者註冊後，Laravel 會發送驗證信。
- 使用者點開信中的 `verify-email/{id}/{hash}` 連結即會觸發 `VerifyEmailController`。
- 若尚未驗證，`markEmailAsVerified()` 會更新 `email_verified_at`。
- 成功後導回 `dashboard`（現在重定向到 `feed`）。

---

## 6. 個人資訊編輯與帳戶管理

### 6.1 控制器：`app/Http/Controllers/ProfileController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
```

### 6.2 編輯個人資料請求：`app/Http/Requests/ProfileUpdateRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
```

### 6.3 個人資料編輯頁面：`resources/views/profile/edit.blade.php`

責任：
- 單一頁面整合個資編輯、密碼更新與刪除帳戶功能
- 引入三個 partial：
  - `profile.partials.update-profile-information-form`
  - `profile.partials.update-password-form`
  - `profile.partials.delete-user-form`

### 6.4 個資更新表單：`resources/views/profile/partials/update-profile-information-form.blade.php`

責任：
- 以 `PATCH /profile` 更新姓名與 Email
- 若 Email 改變，清除 `email_verified_at` 以要求重新驗證
- 顯示驗證信尚未送出的提示
- 支援重新發送驗證信

### 6.5 密碼更新表單：`resources/views/profile/partials/update-password-form.blade.php`

責任：
- 以 `PUT /password` 更新使用者密碼
- 需輸入目前密碼、新密碼、確認密碼
- 更新成功後顯示 `password-updated` 訊息

### 6.6 帳戶刪除表單：`resources/views/profile/partials/delete-user-form.blade.php`

責任：
- 提供 Modal 確認刪除帳戶
- 使用 `DELETE /profile`
- 需輸入密碼確認
- 刪除後登出並導向 `/`

### 6.7 更新密碼控制器：`app/Http/Controllers/Auth/PasswordController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
```

---

## 7. 相關 Blade 頁面清單

- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/reset-password.blade.php`
- `resources/views/auth/verify-email.blade.php`
- `resources/views/profile/edit.blade.php`
- `resources/views/profile/partials/update-profile-information-form.blade.php`
- `resources/views/profile/partials/update-password-form.blade.php`
- `resources/views/profile/partials/delete-user-form.blade.php`

## 8. 整體流程圖

1. 未登入使用者訪問 `register` / `login`。
2. 註冊成功後自動登入並導向 `dashboard`，實際重定向到 `feed`。
3. 登入成功後可訪問 `feed`、`profile` 等保護頁面。
4. 若啟用 Email 驗證，使用者須經由 `verify-email` 頁面完成驗證。
5. 忘記密碼可申請重設連結；點擊信件後回到 `reset-password`。
6. 已登入使用者可在 `profile.edit` 編輯姓名/Email、更新密碼、刪除帳戶。

## 9. 核心邏輯重點

- `LoginRequest` 使用速率限制防護 `Auth::attempt`。
- `RegisteredUserController@store` 透過 `event(new Registered($user))` 觸發 Email 驗證。
- `ProfileController@update` 若 Email 更改，會將 `email_verified_at` 設為 null。
- `PasswordController@update` 會先驗證 `current_password`，再更新密碼。
- `ProfileController@destroy` 會在刪除前先登出使用者，並清除 Session。

---

## 10. 進一步閱讀建議

若要理解此系統如何與社交互動功能結合，可同時閱讀：
- `routes/web.php` 中的 `auth` 中介層與 `feed` 路由
- `app/Models/User.php` 的 `Authenticatable` 設定
- `resources/views/layouts/navigation.blade.php` 的登入狀態顯示
