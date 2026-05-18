<?php

namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Message;
use App\Models\User;
class MessageFactory extends Factory
   {
       protected $model = Message::class;

       public function definition(): array
       {
           return [
               'content' => $this->faker->sentence(),
               'user_id' => User::factory(), //
// 自動為此留言建立一個 User
               'parent_id' => null,
               'depth' => 0,
               'image_path' => null,
           ];
       }
   }
