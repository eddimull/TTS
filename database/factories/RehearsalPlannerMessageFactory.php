<?php

namespace Database\Factories;

use App\Models\RehearsalPlannerMessage;
use App\Models\RehearsalPlannerSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class RehearsalPlannerMessageFactory extends Factory
{
    protected $model = RehearsalPlannerMessage::class;

    public function definition(): array
    {
        return [
            'session_id' => RehearsalPlannerSession::factory(),
            'role'       => 'assistant',
            'content'    => $this->faker->sentence(),
            'payload'    => null,
            'status'     => 'complete',
        ];
    }
}
