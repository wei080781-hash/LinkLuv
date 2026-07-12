<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageId;
    public $parentId;
    public $rootId;

    public function __construct($messageId, $parentId = null, $rootId = null)
    {
        $this->messageId = $messageId;
        $this->parentId = $parentId;
        $this->rootId = $rootId;
    }

    public function broadcastOn()
    {
        return new Channel('wall-channel');
    }

    public function broadcastAs()
    {
        return 'message.deleted';
    }

    public function broadcastWith()
    {
        return [
            'messageId' => $this->messageId,
            'parentId' => $this->parentId,
            'rootId' => $this->rootId,
        ];
    }
}