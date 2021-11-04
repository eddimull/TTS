<?php

namespace Database\Factories;

use App\Models\Bands;
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
        return [
            'name' => $this->faker->name,
            'site_name' => str_replace(' ','_',$this->faker->name)
        ];
    }

}
