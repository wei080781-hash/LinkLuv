# 使用 Google 帳號登入系統實作指南

本指南說明如何在 Laravel 專案中整合 Google OAuth 進行第三方登入。

## 1. 準備工作 (Google Cloud Console)
1. 進入 [Google Cloud Console](https://console.cloud.google.com/)。
2. 建立一個新專案或使用現有專案。
3. 前往 **API 與服務 > OAuth 同意畫面**，設定 App 名稱與使用者支援 Email。
4. 前往 **API 與服務 > 憑證**，點擊 **建立憑證 > OAuth 用戶端 ID**。
5. 選擇 **網頁應用程式**，設定授權的重新導向 URI：
   * `http://localhost:8000/auth/google/callback`
6. 複製產生的 **用戶端 ID (Client ID)** 與 **用戶端密鑰 (Client Secret)**。

## 2. 環境設定
在 `.env` 檔案中加入 Google 憑證：
```env
GOOGLE_CLIENT_ID=您的_CLIENT_ID
GOOGLE_CLIENT_SECRET=您的_CLIENT_SECRET
GOOGLE_REDIRECT=http://localhost:8000/auth/google/callback
```

安裝 Socialite 套件：
```bash
composer require laravel/socialite
```

設定 `config/services.php`：
```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT'),
],
```

## 3. 建立 Controller
建立 `app/Http/Controllers/Auth/GoogleController.php`：

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::updateOrCreate([
            'email' => $googleUser->getEmail(),
        ], [
            'name' => $googleUser->getName(),
            'password' => Hash::make(uniqid()), // Google 登入無需密碼
        ]);

        Auth::login($user);

        return redirect('/dashboard');
    }
}
```

## 4. 定義路由
在 `routes/auth.php/Route::middleware('guest')->group(...)`加入：  
```php
use App\Http\Controllers\Auth\GoogleController;

Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('google.login');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);
```

## 5. 前端按鈕
在登入頁面 (`resources/views/auth/login.blade.php`) 新增連結：
```html
<div class="mt-4">
        <a href="{{ route('google.login') }}" 
           class="flex items-center justify-center w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            使用 Google 帳號登入
        </a>
    </div>
```
### 6 問題與方法:
Illuminate\Contracts\Container\BindingResolutionException
vendor\laravel\framework\src\Illuminate\Container\Container.php:1124
Target class [App\Http\Controllers\Auth\GoogleController] does not exist.

    # Illuminate\Contracts\Container\BindingResolutionException - Internal Server Error

Target class [App\Http\Controllers\Auth\GoogleController] does not exist.

PHP 8.2.12
Laravel 12.58.0
localhost

## Stack Trace

0 - vendor\laravel\framework\src\Illuminate\Container\Container.php:1124
1 - vendor\laravel\framework\src\Illuminate\Container\Container.php:933
2 - vendor\laravel\framework\src\Illuminate\Foundation\Application.php:1078
3 - vendor\laravel\framework\src\Illuminate\Container\Container.php:864
4 - vendor\laravel\framework\src\Illuminate\Foundation\Application.php:1058
5 - vendor\laravel\framework\src\Illuminate\Routing\Route.php:286
6 - vendor\laravel\framework\src\Illuminate\Routing\Route.php:266
7 - vendor\laravel\framework\src\Illuminate\Routing\Route.php:211
8 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:822
9 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:180
10 - vendor\laravel\framework\src\Illuminate\Auth\Middleware\RedirectIfAuthenticated.php:47
11 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
12 - vendor\laravel\framework\src\Illuminate\Routing\Middleware\SubstituteBindings.php:50
13 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
14 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken.php:87
15 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
16 - vendor\laravel\framework\src\Illuminate\View\Middleware\ShareErrorsFromSession.php:48
17 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
18 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:120
19 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:63
20 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
21 - vendor\laravel\framework\src\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse.php:36
22 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
23 - vendor\laravel\framework\src\Illuminate\Cookie\Middleware\EncryptCookies.php:74
24 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
25 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:137
26 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:821
27 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:800
28 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:764
29 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:753
30 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php:200
31 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:180
32 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php:21
33 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull.php:31
34 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
35 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php:21
36 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TrimStrings.php:51
37 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
38 - vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePostSize.php:27
39 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
40 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance.php:109
41 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
42 - vendor\laravel\framework\src\Illuminate\Http\Middleware\HandleCors.php:61
43 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
44 - vendor\laravel\framework\src\Illuminate\Http\Middleware\TrustProxies.php:58
45 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
46 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks.php:22
47 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
48 - vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePathEncoding.php:26
49 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
50 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:137
51 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php:175
52 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php:144
53 - vendor\laravel\framework\src\Illuminate\Foundation\Application.php:1220
54 - public\index.php:20

## Request

GET /auth/google

## Headers

* **host**: localhost
* **connection**: keep-alive
* **sec-ch-ua**: "Chromium";v="148", "Google Chrome";v="148", "Not/A)Brand";v="99"
* **sec-ch-ua-mobile**: ?0
* **sec-ch-ua-platform**: "Windows"
* **upgrade-insecure-requests**: 1
* **user-agent**: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36
* **accept**: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7
* **sec-fetch-site**: same-origin
* **sec-fetch-mode**: navigate
* **sec-fetch-user**: ?1
* **sec-fetch-dest**: document
* **referer**: http://localhost/login
* **accept-encoding**: gzip, deflate, br, zstd
* **accept-language**: zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7
* **cookie**: XSRF-TOKEN=eyJpdiI6ImtxQ05XdlNjQWpyYzBmeDlOYjRmWGc9PSIsInZhbHVlIjoiL0ZoSk5OaWNqczdzZzZ2enhkcDNpYm9IZE01VjZrRHdPSUJpT2hzRVhSSDFvSC9zQVg3L0RSWUZvMWpzRVhyQ1RMdHhhYnVleXh2YUFuankyM2VaNXppU1M5dHNUeXRFTm5xdTRMcjlmNExJdDU5Qzh2bE5kcGkvV3pnbVVXZ1giLCJtYWMiOiJmNjZhM2VhODNkY2Q3MmM1OGIwOTNmOTA2NDc5N2U4OTA0NDI4Yjg1OTY0ZmViMDcwYjY1ZjJjNTZlNjVhNjZhIiwidGFnIjoiIn0%3D; linkluvproject-session=eyJpdiI6IkRPWWRaUmcvWnRvZE5FaTFuM1BmL0E9PSIsInZhbHVlIjoiR3FSMmJFR0RFL081YnIyTUZNN0h0Mk5Da0dPdDNyRzZFK0lGMGxtRmk4M0tKNXV0TzRJR1ZBRzAxQ1JZZWp3RmdVT0NTZWFZeVJDUzFBdUpoc2l6Y3BxaS9zWUhZNkpWcjI2aUJ0S0FrWmNUd0lORGE0VFhjeDBmNGJXamNENUMiLCJtYWMiOiJhOWZhM2E4MDc3ZTEwNzA0NGY1ZTZiMGYxMDkxYjcyZTViMDQ5NzM5YjdiOTI3ZDU1N2NkNmFiNWE5MTRkNzRjIiwidGFnIjoiIn0%3D

## Route Context

controller: App\Http\Controllers\Auth\GoogleController@redirectToGoogle
route name: google.login
middleware: web, guest

## Route Parameters

No route parameter data available.

## Database Queries

* mysql - select * from `sessions` where `id` = 'gChrw4ukVHz4FyozNirNrOq9DGXgAwIDERkrc02D' limit 1 (2.48 ms)

### 解決方法
錯誤訊息 Target class [App\Http\Controllers\Auth\GoogleController] does not exist 的核心含義是：Laravel 在指定的命名空間中找不到這個 Controller 檔案。

  當你在路由中指定 [GoogleController::class, '...']，Laravel 會根據你 use 的命名空間去尋找實體檔案。

  這通常是因為以下三個層面之一出了問題，請逐一檢查：

  1. 檔案路徑與命名空間不匹配 (Namespace Mismatch)
  這是最常見的原因。
   * 檢查檔案位置：你的實體檔案是否真的放在 app/Http/Controllers/Auth/ 目錄下？
   * 檢查命名空間宣告：打開該 Controller 檔案，第一行 namespace 是否寫著 namespace App\Http\Controllers\Auth;？
       * 如果不一致（例如檔案在 Auth 資料夾但 namespace 寫了 App\Http\Controllers），Laravel 就會找不到它。

  2. routes/auth.php 中的引用錯誤
  請檢查你的 routes/auth.php 檔案：
   * 你是否有在該檔案的最上方加入 use App\Http\Controllers\Auth\GoogleController;？
   * 如果沒有 use 語句，或者 use 的路徑寫錯（例如少寫了 Auth），Laravel 路由系統就會嘗試在錯誤的位置尋找該類別。

  3. 類別名稱大小寫或自動載入問題
   * 檔案名稱：PHP 是區分大小寫的。請確認檔案名稱是否完全正確地命名為 GoogleController.php (G 和 C 都要大寫)。
   * Composer Autoload：雖然不太常見，但如果你剛新增檔案，Laravel 有時會因為快取沒更新而找不到。你可以嘗試在終端機執行 composer dump-autoload，強制 Laravel 重新掃描並建立所有類別的路徑映射。

  你的自我偵錯步驟建議：

   1. 核對路徑與命名空間：拿著你的檔案目錄結構，對照 GoogleController.php 的第一行 namespace，確保它們是完全吻合的（Auth 資料夾對應 \Auth 命名空間）。
   2. 確認 use 語句：檢查 routes/auth.php 是否正確引入了這個 Controller 的類別路徑。
   3. 清除快取：執行 php artisan route:clear 後，重新嘗試存取該網址。

  請檢查以上這幾點，看看是否哪裡漏掉了一個細節（例如路徑少打了一個 Auth，或是檔名大小寫不對）。這類錯誤通常都是因為這些細微的不一致造成的。

  答案:我在 app\Http\Controllers\Auth\GoogleController.php 寫成了{GoogleControlloer.php} 把她修修改即可。

  ### 問題{google 錯誤 400：invalid_request                                                                  要求詳情： flowName=GeneralOAuthFlow}

   出現 Google 錯誤 400: invalid_request，且顯示 flowName=GeneralOAuthFlow，這通常不是你程式碼邏輯的問題，而是 Google Cloud Console 的設定與你 Laravel .env 中的設定不匹配。

  這是一個非常典型的設定錯誤，請依照以下順序檢查：

  1. 最常見原因：重新導向 URI (Redirect URI) 不匹配
  這是 99% 的原因。Google 對於「重新導向 URI」的審核非常嚴格，連一個斜線、一個字母的大小寫、或者 HTTP/HTTPS 的差別都不能有偏差。

   * 請檢查你的 .env 設定：

   1     GOOGLE_REDIRECT=http://localhost:8000/auth/google/callback
   * 請檢查你的 Google Cloud Console：
       * 進入 API 與服務 > 憑證 > 點擊你建立的 OAuth 用戶端 ID。
       * 找到 「已授權的重新導向 URI (Authorized redirect URIs)」。
       * 確認這裡填寫的網址，與 .env 中的 GOOGLE_REDIRECT 完全一致。
       * 注意：確認後面有沒有多一個斜線 /。如果你 .env 寫的是 .../callback，GCP 就要寫 .../callback。

  2. 應用程式還在「測試模式」的憑證問題
  如果你的 Google Cloud 專案還沒發布 (還在 Testing)，請確保：
   * 測試使用者：你已經將你用來測試的那個 Gmail 帳號，加入到「OAuth 同意畫面」的 「測試使用者 (Test Users)」 清單中。
   * 如果沒有加進去，Google 會直接回傳 400 錯誤，因為他不允許未經授權的用戶登入測試中的 App。

  3. Client ID 或 Client Secret 貼錯
  請再次打開 Google Cloud Console 複製這兩組資料，並檢查 .env：
   * 是否有不小心貼到額外的空格？
   * 確認 GOOGLE_CLIENT_ID 和 GOOGLE_CLIENT_SECRET 的值有沒有貼反？

  4. 解決後的操作 (務必執行)
  每次修改 .env 後，Laravel 都會快取舊的設定值。請務必在終端機執行：

   1 php artisan config:clear
   2 php artisan cache:clear

  如何偵錯？
  當你點擊「使用 Google 帳號登入」時，觀察瀏覽器的網址列：
   * 網址中會包含一個 redirect_uri=... 的參數。
   * 檢查這個參數的內容，是不是真的是 http://localhost:8000/auth/google/callback？
   * 如果這裡面的網址和你 Google Cloud Console 裡設定的不一樣，那就是問題核心。

  建議嘗試：
  先去確認 Google Cloud Console 裡的 「已授權的重新導向 URI」，並確保它與你的 .env 設定完全相同。通常只要這裡對齊了，400 錯誤就會消失。                                                                             

### google 有傳出去但是無法登入localhost 網站

無法連上網站 local host 拒絕連線                                                                                                                                                                             
   http://localhost:8000/auth/google/callback?state=Ko17FGe4GMhLAnjNGGUR94VNlVzOU6LtzdqEb72L&iss=https%3A%2F%2Faccounts.google.com&code=4%2F0AeoWuM9rjBWPRQ6sCRUARaV7JoclUeBTstvadaSf64fkdR1-wKYC4K3mkT3CmrPba5 
   p_6g&scope=email+profile+https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fuserinfo.profile+https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fuserinfo.email+openid&authuser=0&prompt=consent                                
   Referrer Policy                                                                                                                                                                                              
   strict-origin-when-cross-origin                                                                                                                                                                              
   sec-ch-ua                                                                                                                                                                                                    
   "Chromium";v="148", "Google Chrome";v="148", "Not/A)Brand";v="99"                                                                                                                                            
   sec-ch-ua-mobile                                                                                                                                                                                             
   ?0                                                                                                                                                                                                           
   sec-ch-ua-platform                                                                                                                                                                                           
   "Windows"                                                                                                                                                                                                    
   upgrade-insecure-requests                                                                                                                                                                                    
   1                                                                                                                                                                                                            
   user-agent                                                                                                                                                                                                   
   Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36                                                                                              
   APP_NAME=LinkLuvProject                                                                                                                                                                                      
   APP_ENV=local                                                                                                                                                                                                
   APP_KEY=base64:RPckCWByStF8c58zsB9l6/eJWQcr7Lafz4gFjzVFNDU=                                                                                                                                                  
   APP_DEBUG=true                                                                                                                                                                                               
   APP_URL=http://localhost:8000/auth/google/callback                                                                                                                                                           
                                                                                                                                                                                                                
   # google oauth 憑證                                                                                                                                                                                          
   GOOGLE_CLIENT_ID=你的Google用戶端ID.apps.googleusercontent.com                                                                                                                    
   GOOGLE_CLIENT_SECRET=你的Google用戶端密鑰                                                                                                                                                    
   GOOGLE_REDIRECT=http://localhost:8000/auth/google/callback                                                                                                                                                   
                                                                                                                                                                                                                
   APP_LOCALE=en                                                                                                                                                                                                
   APP_FALLBACK_LOCALE=en                                                                                                                                                                                       
   APP_FAKER_LOCALE=en_US                                                                                                                                                                                       
                                                                                                                                                                                                                
   APP_MAINTENANCE_DRIVER=file                                                                                                                                                                                  
   # APP_MAINTENANCE_STORE=database                                                                                                                                                                             
                                                                                                                                                                                                                
   # PHP_CLI_SERVER_WORKERS=4                                                                                                                                                                                   
                                                                                                                                                                                                                
   BCRYPT_ROUNDS=12                                                                                                                                                                                             
                                                                                                                                                                                                                
   LOG_CHANNEL=stack                                                                                                                                                                                            
   LOG_STACK=single                                                                                                                                                                                             
   LOG_DEPRECATIONS_CHANNEL=null                                                                                                                                                                                
   LOG_LEVEL=debug                                                                                                                                                                                              
                                                                                                                                                                                                                
   DB_CONNECTION=mysql                                                                                                                                                                                          
   DB_HOST=127.0.0.1                                                                                                                                                                                            
   DB_PORT=3306                                                                                                                                                                                                 
   DB_DATABASE=linkluv                                                                                                                                                                                          
   DB_USERNAME=root                                                                                                                                                                                             
   DB_PASSWORD=                                                                                                                                                                                                 
                                                                                                                                                                                                                
   SESSION_DRIVER=database                                                                                                                                                                                      
   SESSION_LIFETIME=120                                                                                                                                                                                         
   SESSION_ENCRYPT=false                                                                                                                                                                                        
   SESSION_PATH=/                                                                                                                                                                                               
   SESSION_DOMAIN=null                                                                                                                                                                                          
                                                                                                                                                                                                                
   BROADCAST_CONNECTION=log                                                                                                                                                                                     
   FILESYSTEM_DISK=local                                                                                                                                                                                        
   QUEUE_CONNECTION=database                                                                                                                                                                                    
                                                                                                                                                                                                                
   CACHE_STORE=database                                                                                                                                                                                         
   # CACHE_PREFIX=                                                                                                                                                                                              
                                                                                                                                                                                                                
   MEMCACHED_HOST=127.0.0.1                                                                                                                                                                                     
                                                                                                                                                                                                                
   REDIS_CLIENT=phpredis                                                                                                                                                                                        
   REDIS_HOST=127.0.0.1                                                                                                                                                                                         
   REDIS_PASSWORD=null                                                                                                                                                                                          
   REDIS_PORT=6379                                                                                                                                                                                              
                                                                                                                                                                                                                
   MAIL_MAILER=log                                                                                                                                                                                              
   MAIL_SCHEME=null                                                                                                                                                                                             
   MAIL_HOST=127.0.0.1                                                                                                                                                                                          
   MAIL_PORT=2525                                                                                                                                                                                               
   MAIL_USERNAME=null                                                                                                                                                                                           
   MAIL_PASSWORD=null                                                                                                                                                                                           
   MAIL_FROM_ADDRESS="hello@example.com"                                                                                                                                                                        
   MAIL_FROM_NAME="${APP_NAME}"                                                                                                                                                                                 
                                                                                                                                                                                                                
   AWS_ACCESS_KEY_ID=                                                                                                                                                                                           
   AWS_SECRET_ACCESS_KEY=                                                                                                                                                                                       
   AWS_DEFAULT_REGION=us-east-1                                                                                                                                                                                 
   AWS_BUCKET=                                                                                                                                                                                                  
   AWS_USE_PATH_STYLE_ENDPOINT=false                                                                                                                                                                            
                                                                                                                                                                                                                
   VITE_APP_NAME="${APP_NAME}"                                                                                                                                                                                  
▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀
✦ 這是一個非常好的訊號！你的 Google 登入流程已經成功跑完了（Google 已經把 code 和 state 傳回來給你了），現在是因為你的 Laravel 伺服器沒有在執行，所以瀏覽器連不上。

  1. 解決「拒絕連線」問題
  你需要讓你的 Laravel 伺服器動起來。請在終端機 (VS Code 的 Terminal) 輸入以下指令：

   1 php artisan serve

  執行後，你會看到類似 Server running on [http://127.0.0.1:8000] 的訊息。請不要關閉這個終端機視窗。

  ---

  2. 修正 .env 中的一個危險設定
  我發現你的 .env 檔案中，APP_URL 被設定成 callback 的路徑了，這是不對的。請馬上修正它，否則以後你的網站會出現很多連結錯誤。

  請將 .env 中的這行：
  APP_URL=http://localhost:8000/auth/google/callback

  改回：
  APP_URL=http://localhost:8000

  ---

  3. 接下來會發生什麼？
  當你執行 php artisan serve 之後，再回到剛剛那個報錯的網址，按下重新整理 (F5)：

   * 如果成功：Laravel 會讀取 GoogleController@handleGoogleCallback，把 Google 給的 code 換成使用者的資訊，然後自動登入並轉跳到 /dashboard。
   * 如果出現 500 錯誤：那代表你的 GoogleController 程式碼或 Service 可能還有小地方需要偵錯。如果發生這個情況，請把螢幕上看到的錯誤訊息（或是 Laravel Log 裡的內容）貼給我看。

  總結建議：
   1. 先執行 php artisan serve 把伺服器開起來。
   2. 修改 .env 把 APP_URL 改回 http://localhost:8000。
   3. 清除快取：php artisan config:clear。
   4. 按 F5 重新整理頁面。

  你的 Google 認證流程已經跨過了最困難的障礙，現在只需把伺服器開啟即可！
   