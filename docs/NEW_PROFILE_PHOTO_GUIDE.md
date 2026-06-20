# 圓形大頭貼與更換功能實作指南 (Service Pattern 版)

本指南採用更穩健的 Service Pattern 架構，實現使用者頭像上傳、預覽與即時更新功能。

## 1. 架構優勢
*   **職責分離**：檔案處理邏輯封裝於 `Service`。
*   **統一入口**：透過 Model 的 `Attribute` 自動處理預設圖與路徑。
*   **體驗優化**：包含前端即時預覽與大小驗證。

---

## 2. 實作步驟

### 第一步：資料庫遷移 (Migration)
在 `users` 表新增圖片路徑欄位。
我們新建一個Migration
1. 保持乾淨：原始的 migration
      檔案代表了資料庫的「起點」。修改它會造成歷史紀錄混亂，特別是在團隊協作時。
2. 避免衝突：如果團隊中其他人已經執行過該
      migration，你修改它並不會讓資料庫產生變化（因為 Laravel
      已經紀錄該檔案執行過了）。

終端機執行以下指令來新增一個 migration 檔案：

   1 php artisan make:migration add_profile_photo_path_to_users_table
     --table=users

  這會自動在 database/migrations
  資料夾下產生一個新的檔案（檔名開頭會有當前時間）。打開那個檔案並填入以下內容：

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('profile_photo_path', 2048)->nullable()->after('email');
});


public function down(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('profile_photo_path');
            });
    }
```


執行：`php artisan migrate` 與 `php artisan storage:link`。

### 第二步：建立服務類別 (Service)
**位置**: `app/Services/ProfilePhotoService.php`
```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoService
{
    public function update(User $user, UploadedFile $photo): void
    {
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
        $user->profile_photo_path = $photo->store('profile-photos', 'public');
    }

    public function delete(User $user): void
    {
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->profile_photo_path = null;
        }
    }
}
```

### 第三步：更新 User Model
**位置**: `app/Models/User.php`
```php
<?php

 namespace App\Models;

 // ... 其他原本的 use 宣告 ...
 use Illuminate\Foundation\Auth\User as Authenticatable;
 use Illuminate\Notifications\Notifiable;
 use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo_path', // <--- 請新增這一行
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 設定 append，讓 Blade 可以透過 $user->profile_photo_url 取用
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * 新增取得頭像路徑的 Attribute
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        return $this->profile_photo_path
            ? asset('storage/' . $this->profile_photo_path)
            : asset('images/default-avatar.png');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

### 第四步：驗證規則 (FormRequest)
**位置**: `app/Http/Requests/ProfileUpdateRequest.php`
```php
'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
```

### 第五步：更新 Controller
**位置**: `app/Http/Controllers/ProfileController.php`
```php
public function __construct(private ProfilePhotoService $photoService) {}

public function update(ProfileUpdateRequest $request): RedirectResponse
    2     {
    3         $user = $request->user();
    4         
    5         // 1. 取得驗證過的資料，並移除檔案物件 (因為檔案不能直接進 fill)
    6         $userData = $request->validated();
    7         unset($userData['profile_photo']); 
    8
    9         // 2. 填入姓名與 Email
   10         $user->fill($userData);
   11
   12         // 3. 如果 Email 有變更，清除驗證時間
   13         if ($user->isDirty('email')) {
   14             $user->email_verified_at = null;
   15         }
   16
   17         // 4. 處理頭像上傳 (透過你的 Service)
   18         if ($request->hasFile('profile_photo')) {
   19             $this->photoService->update($user,
      $request->file('profile_photo'));
   20         }
   21
   22         $user->save();
   23
   24         return Redirect::route('profile.edit')->with('status',
      'profile-updated');
   25     }

1 /**
      * 刪除使用者的帳號 (這是你原本的)
      */
     public function destroy(Request $request): RedirectResponse
     {
         // ... 原本的刪除帳號邏輯 ...
     }

     /**
      * 刪除使用者的頭像 (這是你新增的)
      */
     public function deletePhoto(Request $request): RedirectResponse
     {
         $this->photoService->delete($request->user());
         $request->user()->save();

         return Redirect::route('profile.edit')->with('status',
         'photo-deleted');
     }
```

### 第六步：更新 Blade 視圖
**位置**: `resources/views/profile/partials/update-profile-information-form.blade.php`

為了提供最佳的使用者體驗，我們實作了包含「即時預覽」與「條件式移除按鈕」的 UI。

```html
<section>
    {{-- ... 前面 Header 與 Email 欄位保持不變 ... --}}

    {{-- 注意：必須加上 enctype="multipart/form-data" --}}
    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        {{-- Profile Photo 區塊 --}}
        <div class="flex items-center gap-6">
            <div class="shrink-0">
                <img id="avatar-preview" 
                     src="{{ $user->profile_photo_url }}" 
                     class="w-20 h-20 rounded-full object-cover border">
            </div>

            <div class="flex flex-col gap-2">
                <label for="profile_photo" class="cursor-pointer px-4 py-2 bg-white border rounded-md text-sm hover:bg-gray-50">
                    更換頭像
                </label>
                <input id="profile_photo" name="profile_photo" type="file" class="hidden" accept="image/*">

                @if($user->profile_photo_path)
                    <button type="button" 
                            onclick="document.getElementById('delete-photo').submit()" 
                            class="text-sm text-red-500 hover:text-red-700 text-left">
                        移除頭像
                    </button>
                @endif
            </div>
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />

        {{-- ... 後面的 Name 與 Email 欄位 ... --}}
    </form>

    {{-- 移除頭像用的獨立隱藏 Form --}}
    <form id="delete-photo" action="{{ route('profile.photo.delete') }}" method="POST" class="hidden">
        @csrf
        @method('delete')
    </form>

    {{-- 即時預覽 JavaScript --}}
    <script>
        document.getElementById('profile_photo').onchange = e => {
            if (!e.target.files.length) return;
            const reader = new FileReader();
            reader.onload = ev => {
                document.getElementById('avatar-preview').src = ev.target.result;
            };
            reader.readAsDataURL(e.target.files[0]);
        };
    </script>
</section>

## Symfony\Component\Routing\Exception\RouteNotFoundException - Internal Server Error                                                                        Route [profile.photo.delete] not defined.

這代表你在 Blade 視圖中使用了 route('profile.photo.delete') 這個名稱，但你還沒有在 routes/web.php 裡面定義這個路由。當 Laravel 試圖渲染頁面並尋找這個路由名稱時，找不到它，所以就噴出了錯誤。

  如何修復？

  你需要完成以下兩個步驟：

  第一步：在 routes/web.php 定義路由

  請打開 routes/web.php，並在處理 Profile 相關路由的地方（通常是在 Route::middleware('auth')->group(...) 裡面），加入這行：

   1 // 這裡的 ProfileController::class 必須確保你已經在檔案上方 import 了
   <!-- 新增這行 -->
   2 Route::delete('profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');

  注意：因為你的表單使用了 @method('delete')，所以這裡必須使用 Route::delete。

  第二步：在 ProfileController.php 實作方法

  請確保你的 app/Http/Controllers/ProfileController.php 裡面已經有 deletePhoto 這個方法。根據我們之前的規劃，它應該長得像這樣：

 // 首先確保在檔案頂部有引入 Service
 use App\Services\ProfilePhotoService;

 // ...

 class ProfileController extends Controller
 {
     // 使用建構子注入 Service (或者你也可以在方法內實例化)
    public function __construct(private ProfilePhotoService $photoService) {}

       // ... 其他方法 (edit, update...)

       /**
        * 刪除使用者的頭像
        */
    public function deletePhoto(Request $request): RedirectResponse
       {
           // 1. 呼叫 Service 刪除實體檔案並清除資料庫路徑
           $this->photoService->delete($request->user());
           
           // 2. 儲存 User 狀態 (因為 Service 已經改了 $user->profile_photo_path)
            $request->user()->save();
   
            // 3. 回傳並帶上成功的狀態訊息
            return Redirect::route('profile.edit')->with('status', 'photo-deleted');
        }
    }

    照片並按下「Save」，後端也完全不會處理照片，因為原本的 update 方法只會處理 name 和 email 這兩個欄位。

  更重要的是，如果您直接使用 $request->user()->fill($request->validated());，Laravel 會試圖把一個「檔案物件 (UploadedFile Object)」直接塞進資料庫的 profile_photo_path
  欄位（該欄位預期是字串），這會導致錯誤或存入錯誤的格式。

  ---

  🛠️ 建議的修改方式

  您需要將原本的邏輯拆解，先把「文字資料」填入，再單獨用 Service 處理「檔案上傳」。

  請將您的 update 方法改寫成如下：

 public function update(ProfileUpdateRequest $request): RedirectResponse
 {
     $user = $request->user();

     // 1. 取得驗證過的資料
     $userData = $request->validated();

     // 2. 【關鍵步驟】把檔案物件從陣列中移除，因為 fill() 只能處理字串/數字等基本資料
     // 我們要單獨處理照片，不能直接用 fill() 塞進去
        unset($userData['profile_photo']);
   
        // 3. 填入姓名與 Email 等基本資料
        $user->fill($userData);
   
        // 4. 處理 Email 變更時需要清除驗證狀態的邏輯
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
   
        // 5. 【新增】處理頭像上傳 (透過您的 ProfilePhotoService)
        if ($request->hasFile('profile_photo')) {
            // 呼叫 Service 進行檔案儲存與舊檔案刪除
            $this->photoService->update($user, $request->file('profile_photo'));
        }
   
        // 6. 最後統一儲存所有變更
        $user->save();
   
        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    ### 遇到了 Illuminate\Contracts\Container\BindingResolutionException - Internal Server Error                                                                                                               
   Target class [App\Http\Controllers\ProfilePhotoService] does not exist.                                                            
   PHP 8.2.12                                                                                                                                                                                                   
   Laravel 12.58.0                                                                                                                                                                                              
   localhost                                                                                                                                                                                                    
                                                                                                                                                                                                                
   ## Stack Trace 
   
   
   這個錯誤 Target class [App\Http\Controllers\ProfilePhotoService] does not exist 非常明確：

  Laravel 的依賴注入 (Dependency Injection) 無法找到你宣告的 ProfilePhotoService 類別。

  雖然你已經建立了這個檔案，但因為 Laravel 是一個嚴謹的架構，他沒辦法直接「猜」到這個類別，原因通常是 Namespace 命名空間寫錯 或者 類別沒被正確載入。

  🚀 快速修復步驟：

  第一步：檢查 app/Services/ProfilePhotoService.php 的命名空間
  打開該檔案，檢查最上面的 namespace 是否正確：

   1 <?php
   2
   3 namespace App\Services; // 確保這裡不是 App\Http\Controllers
   4
   5 // ...
  如果你的 Controller 寫的是 use App\Services\ProfilePhotoService;，那 ProfilePhotoService.php 的命名空間就必須是 App\Services。請確認檔案中的命名空間是否寫成了 App\Http\Controllers，如果是，請改成
  App\Services。

  第二步：確認 Controller 的引入 (Import)
  打開 app/Http/Controllers/ProfileController.php，確認頭部有正確引用：

   1 namespace App\Http\Controllers;
   2
   3 use App\Services\ProfilePhotoService; // <--- 檢查這行有沒有寫對！
   4 // ...
  如果你在 Controller 裡誤寫成 use App\Http\Controllers\ProfilePhotoService;，Laravel 就會以為這個服務類別放在 Controller 資料夾裡，當然會找不到。

  第三步：清除快取 (最重要的一步)
  有時候 Laravel 的類別映射快取會卡住，導致你改了檔案但他還是認不得。請在終端機執行以下指令：

   1 php artisan config:clear
   2 php artisan cache:clear
   3 php artisan route:clear
   4 php artisan view:clear
  (這能強制 Laravel 重新掃描你的類別目錄)

  ---

  💡 為什麼會出現 Target class [App\Http\Controllers\ProfilePhotoService]？

  注意看錯誤訊息裡的這行：
  Target class [App\Http\Controllers\ProfilePhotoService] does not exist

  這個訊息揭露了真相： Laravel 試圖去 App\Http\Controllers 這個目錄下找 ProfilePhotoService。

  這代表在你的 ProfileController 中，你可能不小心用了 錯誤的 use 語法，或者你根本沒有寫 use，導致 PHP 預設認為該類別就在 App\Http\Controllers 命名空間內。

  請務必檢查 ProfileController.php 的最上方：
   * 是否寫了 use App\Services\ProfilePhotoService;？
   * 如果沒寫，請補上這行。
   * 如果寫了但還是錯，請檢查檔案名稱是不是真的叫 ProfilePhotoService.php (大小寫必須完全一致)。

  檢查完這幾點，重新整理網頁，應該就能解決了！
```
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

