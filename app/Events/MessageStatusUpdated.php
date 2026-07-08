<?php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): Channel
    {
        return new Channel('wall-channel');
    }

    public function broadcastAs(): string
    {
        return 'message.status.updated';
    }

    public function broadcastWith(): array
    {
        // 🔥 在廣播前，確保強行載入 user 關聯，防止 user 變成 null
        $this->message->loadMissing('user');

        return [
            'message' => [
                'id'         => (int) $this->message->id,
                'parent_id'  => $this->message->parent_id ? (int) $this->message->parent_id : null,
                'user_id'    => (int) $this->message->user_id,
                'content'    => $this->message->content,
                'media_type' => $this->message->media_type, // 💡 這一行一定要有，否則前端 buildMediaHtml 會進不去 video 判斷！
                'status'     => $this->message->status,
                'video_path' => $this->message->video_path,
                'created_at' => $this->message->created_at ? $this->message->created_at->toIso8601String() : null,
                'media_type' => $this->message->media_type,
                // 💡 補上完整的 user 關聯包裹，重繪時頭像與名字絕不噴錯崩潰！
                'user' => [
                    'name'              => $this->message->user?->name ?? '未知用戶',
                    'profile_photo_url' => $this->message->user?->profile_photo_url ?? '/images/default-avatar.png',
                ]    
            ]
        ];
    }
}