<?php

namespace Database\Factories;

use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Questionnaires;
use App\Models\QuestionnaireInstances;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnaireInstancesFactory extends Factory
{
    protected $model = QuestionnaireInstances::class;

    public function definition(): array
    {
        $template = Questionnaires::factory()->create();
        return [
            'questionnaire_id' => $template->id,
            'booking_id' => Bookings::factory(),
            'recipient_contact_id' => Contacts::factory(),
            'sent_by_user_id' => User::factory(),
            'name' => $template->name,
            'description' => $template->description,
            'status' => QuestionnaireInstances::STATUS_SENT,
            'sent_at' => now(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => QuestionnaireInstances::STATUS_IN_PROGRESS]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => QuestionnaireInstances::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => QuestionnaireInstances::STATUS_LOCKED,
            'locked_at' => now(),
        ]);
    }
}
