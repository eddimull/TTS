<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Events;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireInstanceMobileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private Bands $band;
    private Bookings $booking;
    private Contacts $contact;
    private Questionnaires $template;
    private string $ownerToken;
    private string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->band = Bands::factory()->create();
        BandOwners::create(['user_id' => $this->owner->id, 'band_id' => $this->band->id]);
        BandMembers::create(['user_id' => $this->member->id, 'band_id' => $this->band->id]);

        setPermissionsTeamId($this->band->id);
        $this->member->assignRole('band-member');

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $this->booking->id,
            'date' => now()->addMonth()->toDateString(),
            'venue_name' => 'Test Venue',
        ]);
        $this->contact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $this->booking->contacts()->attach($this->contact, ['is_primary' => true]);
        $this->template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $this->ownerToken = $this->owner->createToken(
            'test-device', ['mobile', 'read:questionnaires', 'write:questionnaires']
        )->plainTextToken;
        $this->memberToken = $this->member->createToken(
            'test-device', ['mobile', 'read:questionnaires']
        )->plainTextToken;
    }

    private function asOwner(): array
    {
        return [
            'Authorization' => "Bearer {$this->ownerToken}",
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    private function asMember(): array
    {
        return [
            'Authorization' => "Bearer {$this->memberToken}",
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    private function makeInstance(array $attrs = []): QuestionnaireInstances
    {
        return QuestionnaireInstances::create(array_merge([
            'questionnaire_id' => $this->template->id,
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'sent_by_user_id' => $this->owner->id,
            'name' => $this->template->name,
            'description' => '',
            'status' => QuestionnaireInstances::STATUS_SENT,
            'sent_at' => now(),
        ], $attrs));
    }

    public function test_member_can_list_instances_for_questionnaire(): void
    {
        $this->makeInstance();

        $this->withHeaders($this->asMember())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$this->template->id}/instances")
            ->assertOk()
            ->assertJsonCount(1, 'instances')
            ->assertJsonPath('instances.0.status', 'sent')
            ->assertJsonPath('instances.0.recipient_name', $this->contact->name)
            ->assertJsonPath('instances.0.booking.id', $this->booking->id);
    }

    public function test_eligible_bookings_flags_already_sent_and_portal_access(): void
    {
        $this->makeInstance();
        $noPortal = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => false]);
        $this->booking->contacts()->attach($noPortal, ['is_primary' => false]);

        $response = $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$this->template->id}/eligible-bookings")
            ->assertOk()
            ->assertJsonPath('bookings.0.already_sent', true);

        $contacts = collect($response->json('bookings.0.contacts'));
        $this->assertTrue($contacts->firstWhere('id', $this->contact->id)['can_login']);
        $this->assertFalse($contacts->firstWhere('id', $noPortal->id)['can_login']);
    }

    public function test_eligible_bookings_excludes_past_only_bookings(): void
    {
        $past = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $past->id,
            'date' => now()->subMonth()->toDateString(),
            'venue_name' => 'Past Venue',
        ]);

        $response = $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaires/{$this->template->id}/eligible-bookings")
            ->assertOk();

        $ids = collect($response->json('bookings'))->pluck('id');
        $this->assertTrue($ids->contains($this->booking->id));
        $this->assertFalse($ids->contains($past->id));
    }

    public function test_booking_section_returns_instances_and_available_templates(): void
    {
        $this->makeInstance();
        Questionnaires::factory()->create(['band_id' => $this->band->id, 'archived_at' => now()]);

        $this->withHeaders($this->asMember())
            ->getJson("/api/mobile/bands/{$this->band->id}/bookings/{$this->booking->id}/questionnaire-instances")
            ->assertOk()
            ->assertJsonCount(1, 'instances')
            ->assertJsonCount(1, 'available_questionnaires')
            ->assertJsonPath('available_questionnaires.0.id', $this->template->id);
    }

    public function test_instance_detail_decodes_responses_and_resolves_songs(): void
    {
        $instance = $this->makeInstance();
        $textField = $instance->fields()->create([
            'type' => 'short_text', 'label' => 'Name', 'position' => 10,
            'required' => false, 'source_field_id' => 0,
        ]);
        $multiField = $instance->fields()->create([
            'type' => 'multi_select', 'label' => 'Extras', 'position' => 20,
            'required' => false, 'source_field_id' => 0,
            'settings' => ['options' => [['label' => 'A', 'value' => 'a'], ['label' => 'B', 'value' => 'b']]],
        ]);
        $songField = $instance->fields()->create([
            'type' => 'song_picker', 'label' => 'Must play', 'position' => 30,
            'required' => false, 'source_field_id' => 0,
            'settings' => ['purpose' => 'must_play'],
        ]);
        $song = \App\Models\Song::factory()->create(['band_id' => $this->band->id]);
        $instance->responses()->create(['instance_field_id' => $textField->id, 'value' => 'Alice']);
        $instance->responses()->create(['instance_field_id' => $multiField->id, 'value' => json_encode(['a', 'b'])]);
        $instance->responses()->create(['instance_field_id' => $songField->id, 'value' => json_encode([$song->id])]);

        $this->withHeaders($this->asMember())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$instance->id}")
            ->assertOk()
            ->assertJsonPath('instance.fields.0.label', 'Name')
            ->assertJsonPath("instance.responses.{$textField->id}", 'Alice')
            ->assertJsonPath("instance.responses.{$multiField->id}", ['a', 'b'])
            ->assertJsonPath("instance.song_lookup.{$song->id}.title", $song->title);
    }

    public function test_instance_detail_empty_responses_serialize_as_object(): void
    {
        $instance = $this->makeInstance();

        $response = $this->withHeaders($this->asMember())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$instance->id}")
            ->assertOk();

        // PHP arrays would encode {} as [] — the raw JSON must carry objects.
        $this->assertStringContainsString('"responses":{}', $response->getContent());
        $this->assertStringContainsString('"song_lookup":{}', $response->getContent());
    }

    public function test_cross_band_instance_is_404(): void
    {
        $otherBand = Bands::factory()->create();
        $otherBooking = Bookings::factory()->create(['band_id' => $otherBand->id]);
        $foreign = $this->makeInstance(['booking_id' => $otherBooking->id]);

        $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$foreign->id}")
            ->assertStatus(404);
    }

    public function test_owner_can_send_questionnaire(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/bookings/{$this->booking->id}/questionnaires", [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $this->contact->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('instance.status', 'sent')
            ->assertJsonPath('instance.recipient_name', $this->contact->name);

        $this->assertDatabaseHas('questionnaire_instances', [
            'questionnaire_id' => $this->template->id,
            'booking_id' => $this->booking->id,
            'status' => 'sent',
        ]);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->contact, \App\Notifications\QuestionnaireSent::class);
    }

    public function test_send_rejects_contact_without_portal_access(): void
    {
        $noPortal = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => false]);
        $this->booking->contacts()->attach($noPortal, ['is_primary' => false]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/bookings/{$this->booking->id}/questionnaires", [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $noPortal->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('recipient_contact_id');
    }

    public function test_send_rejects_archived_template(): void
    {
        $this->template->update(['archived_at' => now()]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/bookings/{$this->booking->id}/questionnaires", [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $this->contact->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('questionnaire_id');
    }

    public function test_member_cannot_send(): void
    {
        $this->withHeaders($this->asMember())
            ->postJson("/api/mobile/bands/{$this->band->id}/bookings/{$this->booking->id}/questionnaires", [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $this->contact->id,
            ])
            ->assertStatus(403);
    }

    public function test_send_to_cross_band_booking_is_rejected(): void
    {
        $otherBand = Bands::factory()->create();
        $otherBooking = Bookings::factory()->create(['band_id' => $otherBand->id]);

        \Illuminate\Support\Facades\Notification::fake();

        // recipient_contact_id is scoped to the OTHER booking's contacts, so this
        // fails validation (422) before the controller's abort_if(404) is reached.
        // Either status is equally safe — no write, no notification.
        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/bookings/{$otherBooking->id}/questionnaires", [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $this->contact->id,
            ])
            ->assertStatus(422);

        $this->assertSame(0, QuestionnaireInstances::count());
        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_resend_renotifies_without_new_instance(): void
    {
        $instance = $this->makeInstance();
        \Illuminate\Support\Facades\Notification::fake();

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$instance->id}/resend")
            ->assertOk();

        $this->assertSame(1, QuestionnaireInstances::count());
        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->contact, \App\Notifications\QuestionnaireSent::class);
    }

    public function test_lock_and_unlock_recompute_status(): void
    {
        $instance = $this->makeInstance();

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$instance->id}/lock")
            ->assertOk()
            ->assertJsonPath('instance.status', 'locked');
        $this->assertNotNull($instance->fresh()->locked_at);

        // Unlock with no responses reverts to sent.
        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$instance->id}/unlock")
            ->assertOk()
            ->assertJsonPath('instance.status', 'sent');
        $this->assertNull($instance->fresh()->locked_at);

        // With a response present, unlock resolves to in_progress.
        $field = $instance->fields()->create([
            'type' => 'short_text', 'label' => 'Q', 'position' => 10,
            'required' => false, 'source_field_id' => 0,
        ]);
        $instance->responses()->create(['instance_field_id' => $field->id, 'value' => 'x']);
        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$instance->id}/lock");
        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$instance->id}/unlock")
            ->assertOk()
            ->assertJsonPath('instance.status', 'in_progress');
    }

    public function test_destroy_soft_deletes_instance(): void
    {
        $instance = $this->makeInstance();

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$instance->id}")
            ->assertOk();

        $this->assertSoftDeleted('questionnaire_instances', ['id' => $instance->id]);
    }

    public function test_cross_band_instance_write_is_404(): void
    {
        $otherBand = Bands::factory()->create();
        $otherBooking = Bookings::factory()->create(['band_id' => $otherBand->id]);
        $foreign = $this->makeInstance(['booking_id' => $otherBooking->id]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/questionnaire-instances/{$foreign->id}/lock")
            ->assertStatus(404);
    }
}
