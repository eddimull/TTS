<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\RehearsalPlannerSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RehearsalPlannerSessionFactory extends Factory
{
    protected $model = RehearsalPlannerSession::class;

    public function definition(): array
    {
        return [
            'band_id' => Bands::factory(),
            'user_id' => User::factory(),
            'title'   => null,
        ];
    }
}
