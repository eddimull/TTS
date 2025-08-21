<?php

namespace Database\Factories;

use App\Models\Bands;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BandCalendars>
 */
class BandCalendarsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'band_id' => Bands::factory(),
            'calendar_id' => $this->faker->uuid(),
            'type' => $this->faker->randomElement(['booking', 'event', 'public']),
        ];
    }
}
