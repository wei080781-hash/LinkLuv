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
        // 【流程 2️⃣】：使用者「未登入」，此時是「Google 登入」                                                                  
        // ─────────────────────────────────────────────      
        $user = User::where('email', $googleUser->getEmail())->first();

        // 狀況 A：email 存在且是一般帳號（provider = 'local'）
        if ($user && $user->provider === 'local') {
            return redirect('/login')->withErrors([
                'email' => '此信箱已註冊一般帳號，請使用密碼登入。'
            ]);
        }

        // 狀況 B：email 存在且是 Google 帳號（provider = 'google'）
        if ($user && $user->provider === 'google') {
            Auth::login($user);
            return redirect('/dashboard');
        }

        // 狀況 C：email 完全不存在 ➡️ 自動建立新帳號（Google 帳號）
        $newUser = User::create([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'email_verified_at' => now(),
            'provider' => 'google',
        ]);
        
        Auth::login($newUser);

        return redirect('/dashboard');
    }
}