<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Questionnaires;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnairesFactory extends Factory
{
    protected $model = Questionnaires::class;

    public function definition(): array
    {
        return [
            'band_id' => Bands::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
