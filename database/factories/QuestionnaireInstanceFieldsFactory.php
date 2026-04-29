<?php

namespace Database\Factories;

use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnaireInstanceFieldsFactory extends Factory
{
    protected $model = QuestionnaireInstanceFields::class;

    public function definition(): array
    {
        return [
            'instance_id' => QuestionnaireInstances::factory(),
            'source_field_id' => null,
            'type' => 'short_text',
            'label' => $this->faker->sentence(4),
            'required' => false,
            'position' => 10,
            'settings' => null,
            'visibility_rule' => null,
            'mapping_target' => null,
        ];
    }
}
