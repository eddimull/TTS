<?php

namespace Database\Factories;

use App\Models\CalendarAccess;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CalendarAccess>
 */
class CalendarAccessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'band_calendar_id' => \App\Models\BandCalendars::factory(),
            'role' => $this->faker->randomElement(['reader', 'writer', 'owner']),
        ];
    }
}
