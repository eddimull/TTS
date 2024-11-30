<?php

namespace Database\Factories;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BandsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Bands::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $band = $this->faker->company();
        return [
            'name' => $band,
            'site_name' => str_replace(' ', '_', $band)
        ];
    }

    public function withOwners()
    {
        return $this->afterCreating(function (Bands $band)
        {
            $band->owners()->create(['user_id' => User::factory()->create()->id]);
        });
    }
}
