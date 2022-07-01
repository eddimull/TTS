<?php

namespace Database\Factories;

use App\Models\BandMembers;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Bands;

class BandMembersFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BandMembers::class;

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
