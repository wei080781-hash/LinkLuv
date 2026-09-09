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
            Storage::disk('s3')->delete($user->profile_photo_path);
        }

        // 【修改】明確指定 disk 跟 visibility，確保檔案上傳時 ACL 正確設為 public-read
        $user->profile_photo_path = $photo->store('profile-photos', [
            'disk' => 's3',
            'visibility' => 'public',
        ]);
    }

    public function delete(User $user): void
    {
        if ($user->profile_photo_path) {
            Storage::disk('s3')->delete($user->profile_photo_path);
            $user->profile_photo_path = null;
        }
    }
}
