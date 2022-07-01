<?php

namespace Database\Factories;

use App\Models\BandOwners;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Bands;
use App\Models\User;

class BandOwnersFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BandOwners::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id'=>User::factory()->create()->id,
            'band_id'=>Bands::factory()->create()->id
        ];
    }
}
