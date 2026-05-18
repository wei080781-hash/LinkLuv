<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['content', 'user_id', 'parent_id', 'depth', 'image_path'];

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
