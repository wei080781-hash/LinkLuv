<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // 💡 1. 這裡改引入 ShouldBroadcastNow
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow // 💡 2. 這裡改成 implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load('user');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('wall-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }
}