<?php

namespace Database\Factories;

use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnaireResponsesFactory extends Factory
{
    protected $model = QuestionnaireResponses::class;

    public function definition(): array
    {
        $field = QuestionnaireInstanceFields::factory()->create();
        return [
            'instance_id' => $field->instance_id,
            'instance_field_id' => $field->id,
            'value' => $this->faker->sentence,
        ];
    }
}
