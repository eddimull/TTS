<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'token'    => $this->faker->unique()->sha256(),
            'platform' => $this->faker->randomElement(['ios', 'android']),
        ];
    }
}
