<?php

namespace Tests\Feature\Questionnaires;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use App\Notifications\QuestionnaireSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Bookings $booking;
    private Contacts $contact;
    private Questionnaires $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->contact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $this->booking->contacts()->attach($this->contact, ['is_primary' => true]);

        $this->template = Questionnaires::factory()->create(['band_id' => $this->band->id]);
    }

    public function test_band_owner_can_send_questionnaire(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->owner)->post(
            route('bookings.questionnaires.send', [$this->band, $this->booking]),
            [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $this->contact->id,
            ]
        );

        $response->assertStatus(302);

        $this->assertDatabaseHas('questionnaire_instances', [
            'questionnaire_id' => $this->template->id,
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'status' => 'sent',
        ]);

        Notification::assertSentTo($this->contact, QuestionnaireSent::class);
    }

    public function test_send_fails_when_contact_lacks_portal_access(): void
    {
        $this->contact->update(['can_login' => false]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(
                route('bookings.questionnaires.send', [$this->band, $this->booking]),
                [
                    'questionnaire_id' => $this->template->id,
                    'recipient_contact_id' => $this->contact->id,
                ]
            );

        $response->assertStatus(422);
    }

    public function test_send_fails_when_contact_not_on_booking(): void
    {
        $otherContact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(
                route('bookings.questionnaires.send', [$this->band, $this->booking]),
                [
                    'questionnaire_id' => $this->template->id,
                    'recipient_contact_id' => $otherContact->id,
                ]
            );

        $response->assertStatus(422);
    }

    public function test_send_fails_when_template_belongs_to_different_band(): void
    {
        $otherBand = Bands::factory()->create();
        $foreign = Questionnaires::factory()->create(['band_id' => $otherBand->id]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(
                route('bookings.questionnaires.send', [$this->band, $this->booking]),
                [
                    'questionnaire_id' => $foreign->id,
                    'recipient_contact_id' => $this->contact->id,
                ]
            );

        $response->assertStatus(422);
    }

    public function test_send_fails_when_template_archived(): void
    {
        $this->template->update(['archived_at' => now()]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(
                route('bookings.questionnaires.send', [$this->band, $this->booking]),
                [
                    'questionnaire_id' => $this->template->id,
                    'recipient_contact_id' => $this->contact->id,
                ]
            );

        $response->assertStatus(422);
    }

    public function test_resend_does_not_create_new_instance(): void
    {
        Notification::fake();

        $existing = QuestionnaireInstances::factory()->create([
            'questionnaire_id' => $this->template->id,
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'sent_by_user_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('bookings.questionnaires.resend', [$this->band, $this->booking, $existing])
        );

        $response->assertStatus(302);
        $this->assertSame(1, QuestionnaireInstances::where('booking_id', $this->booking->id)->count());
        Notification::assertSentTo($this->contact, QuestionnaireSent::class);
    }

    public function test_lock_and_unlock_changes_status(): void
    {
        $instance = QuestionnaireInstances::factory()->submitted()->create([
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'sent_by_user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('bookings.questionnaires.lock', [$this->band, $this->booking, $instance]))
            ->assertStatus(302);

        $instance->refresh();
        $this->assertSame('locked', $instance->status);
        $this->assertNotNull($instance->locked_at);

        $this->actingAs($this->owner)
            ->post(route('bookings.questionnaires.unlock', [$this->band, $this->booking, $instance]))
            ->assertStatus(302);

        $instance->refresh();
        $this->assertSame('submitted', $instance->status);
        $this->assertNull($instance->locked_at);
    }

    public function test_destroy_soft_deletes_instance(): void
    {
        $instance = QuestionnaireInstances::factory()->create([
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'sent_by_user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('bookings.questionnaires.destroy', [$this->band, $this->booking, $instance]))
            ->assertStatus(302);

        $this->assertSoftDeleted('questionnaire_instances', ['id' => $instance->id]);
    }
}
