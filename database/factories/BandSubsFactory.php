<?php

namespace Database\Factories;

use App\Models\BandSubs;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Bands;

class BandSubsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BandSubs::class;

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
