<?php
namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserProfileUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public User $user) {}

    public function broadcastOn(): Channel
    {
        return new Channel('wall-channel');
    }

    public function broadcastAs(): string
    {
        return 'user.profile.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id'                => (int) $this->user->id,
                'name'              => $this->user->name,
                'profile_photo_url' => $this->user->profile_photo_url,
            ]
        ];
    }
}