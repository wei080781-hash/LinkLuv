<?php

namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Like;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['content', 'user_id', 'parent_id', 'thread_id', 'depth','path', 'image_path'];

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }
    // 這是留言跟父留言的關聯
    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_id');
    }
    // 關聯：留言屬於哪位使用者
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 新增link 關聯
    public function likes() {
        return $this->hasMany(Like::class);
    }
}
