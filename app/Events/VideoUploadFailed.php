<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoUploadFailed implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $userId, public string $message = '影片上傳或轉檔失敗') {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'upload.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}