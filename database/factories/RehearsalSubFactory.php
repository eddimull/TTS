<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Rehearsal;
use Illuminate\Database\Eloquent\Factories\Factory;

class RehearsalSubFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rehearsal_id' => Rehearsal::factory(),
            'band_id'      => Bands::factory(),
            'band_role_id' => null,
            'user_id'      => null,
            'name'         => $this->faker->name(),
            'email'        => $this->faker->unique()->safeEmail(),
            'phone'        => null,
            'notes'        => null,
            'invited_by'   => null,
        ];
    }
}
