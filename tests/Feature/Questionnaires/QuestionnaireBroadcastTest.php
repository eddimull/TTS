<?php

namespace Tests\Feature\Questionnaires;

use App\Events\BandDataChanged;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QuestionnaireBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private Bookings $booking;
    private Contacts $contact;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->contact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
    }

    private function makeInstance(): QuestionnaireInstances
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        return QuestionnaireInstances::create([
            'questionnaire_id' => $template->id,
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'sent_by_user_id' => $this->owner->id,
            'name' => $template->name,
            'description' => '',
            'status' => QuestionnaireInstances::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function test_questionnaire_create_broadcasts(): void
    {
        Event::fake([BandDataChanged::class]);

        $q = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        Event::assertDispatched(BandDataChanged::class, fn (BandDataChanged $e) =>
            $e->bandId === $this->band->id
            && $e->model === 'questionnaires'
            && $e->id === $q->id
            && $e->action === 'created');
    }

    public function test_instance_status_change_broadcasts_with_booking_band(): void
    {
        $instance = $this->makeInstance();
        Event::fake([BandDataChanged::class]);

        $instance->update(['status' => QuestionnaireInstances::STATUS_LOCKED, 'locked_at' => now()]);

        Event::assertDispatched(BandDataChanged::class, fn (BandDataChanged $e) =>
            $e->bandId === $this->band->id
            && $e->model === 'questionnaire_instances'
            && $e->id === $instance->id
            && $e->action === 'updated');
    }

    public function test_response_save_broadcasts_with_instance_band(): void
    {
        $instance = $this->makeInstance();
        $field = $instance->fields()->create([
            'type' => 'short_text', 'label' => 'Name', 'position' => 10, 'required' => false,
            'source_field_id' => 0,
        ]);
        Event::fake([BandDataChanged::class]);

        $response = $instance->responses()->create([
            'instance_field_id' => $field->id,
            'value' => 'hello',
        ]);

        Event::assertDispatched(BandDataChanged::class, fn (BandDataChanged $e) =>
            $e->bandId === $this->band->id
            && $e->model === 'questionnaire_responses'
            && $e->id === $response->id
            && $e->action === 'created');
    }
}
