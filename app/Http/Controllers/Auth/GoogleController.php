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

        // 判斷帳號已存在資料庫並補上google id 去做新登入
        if (!$user->google_id) {
            $user->update(['google_id' => $googleUser->getId()]);
        }

        Auth::login($user);

        return redirect('/dashboard');
    }
}