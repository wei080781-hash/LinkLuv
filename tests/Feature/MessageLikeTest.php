<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_like_a_message()
    {
        $user = User::factory()->create();
        $message = Message::factory()->create(['user_id' => $user->id]);
        
        $this->actingAs($user)
            ->post(route('messages.like', $message))
            ->assertJson(['success' => true, 'liked' => true]);
        $this->assertDatabaseHas('likes', ['user_id' =>     $user->id,
        'message_id' => $message->id,

        ]);    
    }
}
