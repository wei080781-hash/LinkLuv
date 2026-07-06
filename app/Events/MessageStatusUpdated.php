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
        return [
            'message' => [
                'id'         => $this->message->id,
                'status'     => $this->message->status,
                'video_path' => $this->message->video_path,
                // 🔥 【請在內部精準補上這一行黃金通行證】
                'content' => $this->message->content,

            ]
        ];
    }
}