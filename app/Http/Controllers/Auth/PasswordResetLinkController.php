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
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);
        
        // 🔍 【新增安全檢查】：未驗證信箱不得使用忘記密碼功能
        $user = \App\Models\User::where('email', $request->email)->first();
        // ← 新增：檢查是否為一般帳號
        if ($user && $user->provider !== 'local') {
            // 若是 Google 帳號或沒有帳號，統一回成功訊息（不洩漏）
            return back()->with('status', __('已寄送密碼重設連結，請檢查信箱。'));
        }

        // ← 原本的驗證檢查
        if ($user && ! $user->hasVerifiedEmail()) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => '該信箱尚未完成驗證，無法使用忘記密碼功能。']);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
