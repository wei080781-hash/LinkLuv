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
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            return redirect('/login')->withErrors(['google' => 'Google 登入失敗，請重試']);
        } 

        // ─────────────────────────────────────────────                                                                         
        // 【流程 3️⃣】：使用者「已登入」，此時是進行「帳號綁定」                                                                 
        // ─────────────────────────────────────────────        
        if (Auth::check()) {
           $currentUser = Auth::user();
           
           // 檢查 Google 授權的信箱是否與當前登入帳號信箱一致 
           if ($googleUser->getEmail() !== $currentUser->email) {
              return redirect()->route('profile.edit')->withErrors(['google' => 'Google 信箱與目前登入帳號不一致，無法綁定！']);
            }

            $currentUser->update([
               'google_id' => $googleUser->getId(),
            ]);
            
            return redirect()->route('profile.edit')->with('status', 'google-linked');
        }

        // ─────────────────────────────────────────────                                                                         
        // 【流程 2️⃣】：使用者「未登入」，此時是「Google 登入」                                                                  
        // ─────────────────────────────────────────────      
        $user = User::where('email', $googleUser->getEmail())->first();

        // 狀況三：email 存在，但尚未綁定 Google（google_id 是 NULL）
        if ($user && is_null($user->google_id)) {
            return redirect('/login')->withErrors([
                'email' => '此信箱已註冊一般帳號。請先使用密碼登入，再至個人資料頁面綁定 Google。'
            ]);
        }

        // 狀況二：email 存在，且已有 google_id
        if ($user && !is_null($user->google_id)) {
            Auth::login($user);
            return redirect('/dashboard');
        }

        // 狀況一：email 完全不存在 ➡️ 自動建立新帳號（視為已驗證）
        $newUser = User::create([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'password' => null, // 尚未設定本地密碼
            'email_verified_at' => now(), // 視為已驗證
        ]);
        
        // 狀況一：email 完全不存在 ➡️ 自動建立新帳號（視為已驗證


        // catch (\Exception $e) {
        //     return redirect('/login')->withErrors(['google' => '發生錯誤：' . $e->getMessage()]);
        // }

        // // 1. 先透過 Email 尋找是否已有使用者紀錄
        // $user = User::where('email', $googleUser->getEmail())->first();

        // if ($user) {
        //     // 情況 A：帳號已存在（不論是一般帳號還是舊 Google 帳號
        //     // 只補上 google_id，保護原本的 password 不被覆蓋
        //     $user->update([
        //        'google_id' => $googleUser->getId(),
        //        'email_verified_at' => $user->email_verified_at ?? now(),  
        //     ]);
        // } else {
        //     // 情況 B：全新使用者，建立新帳號並給予初始隨機密碼
        // $user = User::updateOrCreate(
        //     ['email' => $googleUser->getEmail()],
        //     [
        //         'name' => $googleUser->getName(),
        //         'google_id' => $googleUser->getId(), // 建議加上
        //         'password' => null, // 👈 改為 null，代表尚未設定本地密碼
        //         'email_verified_at' => now(),
        //     ]);
        // }

        

        Auth::login($newUser);

        return redirect('/dashboard');
    }
}