<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Events;
use App\Models\QuestionnaireApplications;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireRoutingMobileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Bands $band;
    private Bookings $booking;
    private Events $event;
    private QuestionnaireInstances $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->band = Bands::factory()->create();
        BandOwners::create(['user_id' => $this->owner->id, 'band_id' => $this->band->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $this->booking->id,
            'venue_name' => 'Il Mercato',
            'notes' => '',
            'additional_data' => ['wedding' => ['dances' => []]],
        ]);
        $contact = Contacts::factory()->create(['band_id' => $this->band->id]);
        $this->booking->contacts()->attach($contact, ['is_primary' => true]);
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);
        $this->instance = QuestionnaireInstances::create([
            'questionnaire_id' => $template->id,
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $contact->id,
            'sent_by_user_id' => $this->owner->id,
            'name' => $template->name,
            'status' => QuestionnaireInstances::STATUS_SUBMITTED,
            'sent_at' => now(),
            'submitted_at' => now(),
        ]);
    }

    private function headers(array $abilities = ['mobile', 'read:questionnaires', 'write:questionnaires', 'write:events']): array
    {
        $token = $this->owner->createToken('device', $abilities)->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    private function answer(string $type, string $label, string $value, ?string $target = null): QuestionnaireResponses
    {
        $field = $this->instance->fields()->create([
            'type' => $type, 'label' => $label, 'position' => $this->instance->fields()->count() * 10 + 10,
            'required' => false, 'source_field_id' => 0, 'mapping_target' => $target,
        ]);

        return $this->instance->responses()->create(['instance_field_id' => $field->id, 'value' => $value]);
    }

    private function base(): string
    {
        return "/api/mobile/bands/{$this->band->id}";
    }

    public function test_preview_returns_the_documented_shape(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Forever and Ever, Amen by Randy Travis', 'wedding.dance.first');
        $this->answer('short_text', 'Venue Name', 'II Mercato');
        $this->answer('short_text', "Bride's Full Name", 'Jane Doe');

        $this->withHeaders($this->headers())
            ->getJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/routing")
            ->assertOk()
            ->assertJsonStructure([
                'event_id',
                'groups' => [['key', 'label', 'items' => [[
                    'response_id', 'field_label', 'value_display', 'target', 'target_label', 'status', 'default_checked',
                    'compatible_targets' => [['key', 'label']],
                ]]]],
                'conflicts' => [['kind', 'booking_value', 'form_value', 'resolution']],
                'song_requests' => ['must_play', 'do_not_play', 'write_ins'],
                'contacts',
                'unrouted' => [['response_id', 'field_label', 'value_display', 'compatible_targets' => [['key', 'label']]]],
                'targets' => [['key', 'label', 'group']],
            ])
            ->assertJsonPath('groups.0.key', 'timeline')
            ->assertJsonPath('groups.0.items.0.response_id', $first->id)
            ->assertJsonPath('groups.0.items.0.target_label', 'First dance')
            ->assertJsonPath('groups.0.items.0.status', 'pending')
            ->assertJsonPath('conflicts.0.kind', 'venue_mismatch')
            ->assertJsonPath('conflicts.0.resolution', null)
            ->assertJsonPath('unrouted.0.field_label', "Bride's Full Name");
    }

    public function test_route_applies_and_undo_restores(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $headers = $this->headers();

        $route = $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/route", [
                'items' => [['response_id' => $first->id, 'target' => 'wedding.dance.first']],
                'resolutions' => ['songs' => [], 'venue' => null],
            ])
            ->assertOk()
            ->assertJsonPath('applied', 1)
            ->assertJsonPath('event.id', $this->event->id)
            ->assertJsonPath('event.key', $this->event->key)
            ->assertJsonPath('event.additional_data.wedding.dances.0.title', 'First Dance')
            ->assertJsonPath('event.additional_data.wedding.dances.0.data', 'Evergreen');

        $applicationId = $route->json('application_id');
        $this->assertNotNull(QuestionnaireApplications::find($applicationId));

        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-applications/{$applicationId}/undo")
            ->assertOk()
            ->assertJsonPath('undone', true)
            ->assertJsonPath('event.additional_data.wedding.dances', []);

        $this->assertNull($first->fresh()->applied_to_event_at);
    }

    public function test_undo_after_an_event_edit_is_409(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $headers = $this->headers();
        $applicationId = $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/route", [
                'items' => [['response_id' => $first->id, 'target' => 'wedding.dance.first']],
            ])->json('application_id');

        $this->travel(5)->seconds();
        $this->event->fresh()->update(['notes' => 'edited']);

        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-applications/{$applicationId}/undo")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Event was edited since — undo manually');
    }

    public function test_route_validates_targets_and_compatibility(): void
    {
        $onsite = $this->answer('yes_no', 'Onsite?', 'yes', 'wedding.onsite');
        $headers = $this->headers();

        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/route", [
                'items' => [['response_id' => $onsite->id, 'target' => 'nope']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.target');

        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/route", [
                'items' => [['response_id' => $onsite->id, 'target' => 'wedding.dance.first']],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/route", [
                'items' => [],
                'resolutions' => ['songs' => ['12' => 'maybe'], 'venue' => 'neither'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['resolutions.songs.12', 'resolutions.venue']);

        $this->assertNull($onsite->fresh()->applied_to_event_at);
    }

    public function test_routing_endpoints_require_write_events_and_read_questionnaires(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $noEvents = $this->headers(['mobile', 'read:questionnaires']);
        $noQuestionnaires = $this->headers(['mobile', 'write:events']);

        foreach ([$noEvents, $noQuestionnaires] as $headers) {
            $this->withHeaders($headers)
                ->getJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/routing")
                ->assertStatus(403);
            $this->withHeaders($headers)
                ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/route", [
                    'items' => [['response_id' => $first->id, 'target' => 'wedding.dance.first']],
                ])
                ->assertStatus(403);
        }

        $this->assertNull($first->fresh()->applied_to_event_at);
    }

    public function test_cross_band_instance_and_application_are_404(): void
    {
        $otherBand = Bands::factory()->create();
        $otherBooking = Bookings::factory()->create(['band_id' => $otherBand->id]);
        Events::factory()->create(['eventable_type' => Bookings::class, 'eventable_id' => $otherBooking->id]);
        $foreign = QuestionnaireInstances::factory()->submitted()->create(['booking_id' => $otherBooking->id]);
        $application = QuestionnaireApplications::create([
            'instance_id' => $foreign->id,
            'event_id' => $otherBooking->events()->first()->id,
            'user_id' => $this->owner->id,
            'before' => [],
            'applied_response_ids' => [],
        ]);
        $headers = $this->headers();

        $this->withHeaders($headers)
            ->getJson("{$this->base()}/questionnaire-instances/{$foreign->id}/routing")
            ->assertStatus(404);
        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$foreign->id}/route", ['items' => []])
            ->assertStatus(404);
        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-applications/{$application->id}/undo")
            ->assertStatus(404);
    }

    public function test_old_apply_endpoints_still_work(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $headers = $this->headers();

        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/apply-all")
            ->assertOk()
            ->assertJsonPath('applied_count', 1);
        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/append-to-notes")
            ->assertOk();

        $this->assertNotNull($first->fresh()->applied_to_event_at);
    }

    public function test_route_tolerates_absent_resolutions_and_remember_writes_the_template(): void
    {
        $template = $this->instance->questionnaire;
        $source = $template->fields()->create([
            'type' => 'long_text', 'label' => 'Anything else we should know?', 'position' => 10,
            'required' => false,
        ]);
        $field = $this->instance->fields()->create([
            'type' => 'long_text', 'label' => 'Anything else we should know?', 'position' => 10,
            'required' => false, 'source_field_id' => $source->id,
        ]);
        $response = $this->instance->responses()->create(['instance_field_id' => $field->id, 'value' => 'No slow songs']);
        $headers = $this->headers();

        foreach ([[], ['resolutions' => []], ['resolutions' => ['songs' => []]], ['resolutions' => ['venue' => null]]] as $extra) {
            $this->withHeaders($headers)
                ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/route", ['items' => []] + $extra)
                ->assertOk()
                ->assertJsonPath('applied', 0);
        }

        // Incompatible "remember" is rejected before anything is written.
        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/route", [
                'items' => [['response_id' => $response->id, 'target' => 'wedding.onsite', 'remember' => true]],
            ])
            ->assertStatus(422);
        $this->assertNull($source->fresh()->mapping_target);

        $this->withHeaders($headers)
            ->postJson("{$this->base()}/questionnaire-instances/{$this->instance->id}/route", [
                'items' => [['response_id' => $response->id, 'target' => 'performance.notes', 'remember' => true]],
            ])
            ->assertOk()
            ->assertJsonPath('applied', 1)
            ->assertJsonPath('event.key', $this->event->key);

        $this->assertSame('performance.notes', $source->fresh()->mapping_target);
        $this->assertSame('performance.notes', $response->fresh()->routed_target);
    }

    public function test_remember_requires_write_questionnaires(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $headers = $this->headers(['mobile', 'read:questionnaires', 'write:events']);
        $url = "{$this->base()}/questionnaire-instances/{$this->instance->id}/route";

        $this->withHeaders($headers)
            ->postJson($url, ['items' => [['response_id' => $first->id, 'target' => 'wedding.dance.first', 'remember' => true]]])
            ->assertStatus(403);
        $this->assertNull($first->fresh()->applied_to_event_at);

        // Without remember, the same token can send.
        $this->withHeaders($headers)
            ->postJson($url, ['items' => [['response_id' => $first->id, 'target' => 'wedding.dance.first']]])
            ->assertOk();
    }
}
