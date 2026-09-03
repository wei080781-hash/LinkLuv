<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {

        $user = $request->user();

        // 1. 基本驗證規則（新密碼與確認密碼）
        $rules = [
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];

        // 2. 動態判斷：只有當使用者原本「有密碼」時，才要求輸入舊密碼
        if ($user->hasPassword()) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $validated = $request->validateWithBag('updatePassword', $rules);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
