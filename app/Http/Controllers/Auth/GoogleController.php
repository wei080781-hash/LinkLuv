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
            ]
        );

        Auth::login($user);

        return redirect('/dashboard');
    }
}