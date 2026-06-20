<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use App\Services\ProfilePhotoService;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function __construct(private ProfilePhotoService $photoService) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */

    // 更新這樣在才能處理照片跟其他資料
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // 取的驗證過的資料
        $userData = $request->validated();

        // 記錄：更新流程開始
        Log::info("Profile Update Started: User ID {$user->id}");

        // 單獨處理照片，不要fill()塞進去，因為fill() 只能處理字串/數字等基本資料
        unset($userData['profile_photo']);

        // 3. 填入姓名與 Email 等基本資料
        $user->fill($userData);

        // 4. 處理 Email 變更時需要清除驗證狀態的邏輯
        if ($user->isDirty('email')) {
            // 記錄：偵測到 Email 變更
            Log::info("Profile Update: Email change detected for User ID {$user->id}. Clearing email_verified_at.");
            $user->email_verified_at = null;
        }

        // 5. 【新增】處理頭像上傳 (透過您的 ProfilePhotoService)
        if ($request->hasFile('profile_photo')) {
            // 記錄：偵測到頭像上傳
            Log::info("Profile Update: New photo detected for User ID {$user->id}. Calling ProfilePhotoService.");

            $this->photoService->update($user, $request->file('profile_photo'));

            // 記錄：頭像處理完畢
            Log::info("Profile Update: Photo processing completed for User ID {$user->id}. New path: {$user->profile_photo_path}");
        }


        $user->save();

        // 記錄：更新流程結束
        Log::info("Profile Update Completed: User ID {$user->id} saved successfully.");
        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
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

    /**刪除使用者頭像 */
    public function deletePhoto(Request $request): RedirectResponse
    {
        $this->photoService->delete($request->user());
        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'photo-deleted');
    }
}
