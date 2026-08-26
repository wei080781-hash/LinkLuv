<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class VideoUploadCompleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $userId, public Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'upload.completed';
    }

    public function broadcastWith(): array
    {
        $originalFilename = Cache::get(
            'message_original_filename:' . $this->message->id
        );

        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'media_type' => $this->message->media_type,
                'status' => $this->message->status,
                'original_filename' => $originalFilename,
            ],
        ];
    }
}
