<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password', 'profile_photo_path'];

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

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    /**
     * 新增取得頭像路徑的 Attribute
     */
    public function getProfilePhotoUrlAttribute(): string
    {
      if (!$this->profile_photo_path) {
	    return asset('images/default-avatar.svg');
      }

      return \Storage::disk('s3')->url($this->profile_photo_path);

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
