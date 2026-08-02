<?php

namespace Database\Factories;

use App\Models\BandRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BandRole>
 */
class BandRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'band_id' => \App\Models\Bands::factory(),
            'name'    => 'Role ' . $this->faker->unique()->word(),
        ];
    }
}
