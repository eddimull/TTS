<?php

namespace Database\Factories;

use App\Models\Bands;
use Illuminate\Database\Eloquent\Factories\Factory;

class LodgingFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('+1 week', '+2 weeks');
        return [
            'band_id'      => Bands::factory(),
            'name'         => $this->faker->company() . ' Hotel',
            'address'      => $this->faker->address(),
            'check_in_at'  => $checkIn,
            'check_out_at' => (clone $checkIn)->modify('+2 days'),
        ];
    }
}
