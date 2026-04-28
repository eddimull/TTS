<?php

namespace Database\Factories;

use App\Models\QuestionnaireFields;
use App\Models\Questionnaires;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnaireFieldsFactory extends Factory
{
    protected $model = QuestionnaireFields::class;

    public function definition(): array
    {
        return [
            'questionnaire_id' => Questionnaires::factory(),
            'type' => 'short_text',
            'label' => $this->faker->sentence(4),
            'help_text' => null,
            'required' => false,
            'position' => 10,
            'settings' => null,
            'visibility_rule' => null,
            'mapping_target' => null,
        ];
    }

    public function ofType(string $type, array $settings = null): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'settings' => $settings,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn () => ['required' => true]);
    }
}
