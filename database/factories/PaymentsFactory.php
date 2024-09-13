<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payments>
 */
class PaymentsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'amount' => $this->faker->numberBetween(100, 10000),
            'date' => $this->faker->dateTimeThisYear(),
            'band_id' => \App\Models\Bands::factory(),
            'user_id' => \App\Models\User::factory(),
        ];
    }
}
