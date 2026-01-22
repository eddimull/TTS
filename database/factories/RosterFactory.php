<?php

namespace Database\Factories;

use App\Models\Roster;
use App\Models\Bands;
use Illuminate\Database\Eloquent\Factories\Factory;

class RosterFactory extends Factory
{
    protected $model = Roster::class;

    public function definition(): array
    {
        return [
            'band_id' => Bands::factory(),
            'name' => fake()->words(2, true) . ' Roster',
            'description' => fake()->optional()->sentence(),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'name' => 'Default Roster',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
