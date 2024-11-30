<?php

namespace Database\Factories;

use App\Models\Charts;
use App\Models\Bands;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChartsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Charts::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->sentence(3),
            'composer' => $this->faker->name,
            'arranger' => $this->faker->name,
            'description' => $this->faker->paragraph,
            'public' => $this->faker->boolean,
            'price' => $this->faker->numberBetween(0, 10000),  // Price in cents
            'band_id' => Bands::factory(),
        ];
    }

    /**
     * Indicate that the chart is public.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function public()
    {
        return $this->state(function (array $attributes)
        {
            return [
                'public' => true,
            ];
        });
    }

    /**
     * Indicate that the chart is private.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function private()
    {
        return $this->state(function (array $attributes)
        {
            return [
                'public' => false,
            ];
        });
    }
}
