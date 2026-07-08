<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): Channel
    {
        return new Channel('wall-channel');
    }

    public function broadcastAs(): string
    {
        return 'message.created'; // 這是前端監聽的名稱
    }

    public function broadcastWith(): array
    {
        // 強制載入 user 資訊，確保前端渲染不會失敗
        $this->message->loadMissing('user');

        return [
            'message' => [
                'id'         => (int) $this->message->id,
                'parent_id'  => $this->message->parent_id ? (int) $this->message->parent_id : null,
                'content'    => $this->message->content,
                'media_type' => $this->message->media_type,
                'status'     => $this->message->status,
                'video_path' => $this->message->video_path,
                'image_path' => $this->message->image_path,
                'created_at' => $this->message->created_at ? $this->message->created_at->toIso8601String() : null,
                'user'       => [
                    'name'              => $this->message->user?->name ?? '未知用戶',
                    'profile_photo_url' => $this->message->user?->profile_photo_url ?? '/images/default-avatar.png',
                ]
            ]
        ];
    }
}