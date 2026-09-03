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
        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['google' => '發生錯誤：' . $e->getMessage()]);
        }

        // 1. 先透過 Email 尋找是否已有使用者紀錄
        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            // 情況 A：帳號已存在（不論是一般帳號還是舊 Google 帳號
            // 只補上 google_id，保護原本的 password 不被覆蓋
            $user->update([
               'google_id' => $googleUser->getId(),
               'email_verified_at' => $user->email_verified_at ?? now(),  
            ]);
        } else {
            // 情況 B：全新使用者，建立新帳號並給予初始隨機密碼
        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(), // 建議加上
                'password' => Hash::make(uniqid()),
                // 新加上這個條件讓google用戶標記為以驗證
                'email_verified_at' => now(),
            ]
        );

        

        Auth::login($user);

        return redirect('/dashboard');
    }
}