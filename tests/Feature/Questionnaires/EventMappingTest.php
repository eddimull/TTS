<?php

namespace Tests\Feature\Questionnaires;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventMappingTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Bookings $booking;
    private Events $event;
    private QuestionnaireInstances $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $this->booking->id,
            'additional_data' => ['wedding' => ['onsite' => 0, 'dances' => [
                ['title' => 'First Dance', 'data' => 'TBD'],
            ]]],
        ]);

        $this->instance = QuestionnaireInstances::factory()->create(['booking_id' => $this->booking->id]);
    }

    public function test_apply_response_writes_yes_no_to_event(): void
    {
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);

        $this->actingAs($this->owner)->post(
            route('events.questionnaires.apply_response', [
                'event' => $this->event->id,
                'instance' => $this->instance->id,
                'response' => $response->id,
            ])
        )->assertStatus(302);

        $this->event->refresh();
        $this->assertSame(true, data_get($this->event->additional_data, 'wedding.onsite'));
    }

    public function test_apply_all_applies_every_unapplied_mapped_response(): void
    {
        $f1 = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $f2 = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'short_text',
            'mapping_target' => 'wedding.dance.first',
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $f1->id,
            'value' => 'yes',
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $f2->id,
            'value' => 'Evergreen',
        ]);

        $this->actingAs($this->owner)->post(
            route('events.questionnaires.apply_all', [$this->event->id, $this->instance->id])
        )->assertStatus(302);

        $this->event->refresh();
        $this->assertSame(true, data_get($this->event->additional_data, 'wedding.onsite'));
        $dances = data_get($this->event->additional_data, 'wedding.dances');
        $this->assertSame('Evergreen',
            data_get(collect($dances)->firstWhere('title', 'First Dance'), 'data')
        );
    }

    public function test_append_all_to_notes_appends_block(): void
    {
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'short_text',
            'label' => 'Bride',
            'position' => 10,
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Jane',
        ]);

        $this->actingAs($this->owner)->post(
            route('events.questionnaires.append_to_notes', [$this->event->id, $this->instance->id])
        )->assertStatus(302);

        $this->event->refresh();
        $this->assertStringContainsString('Bride', $this->event->notes);
        $this->assertStringContainsString('Jane', $this->event->notes);
    }

    public function test_apply_requires_questionnaires_read_permission(): void
    {
        $stranger = User::factory()->create();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);

        $this->actingAs($stranger)->post(
            route('events.questionnaires.apply_response', [
                'event' => $this->event->id,
                'instance' => $this->instance->id,
                'response' => $response->id,
            ])
        )->assertStatus(403);
    }

    public function test_routing_preview_route_and_undo_json(): void
    {
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'long_text',
            'label' => 'Names and order of toasts',
            'mapping_target' => 'wedding.toasts',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Best man, then maid of honor',
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('events.questionnaires.routing', [$this->event->id, $this->instance->id]))
            ->assertOk()
            ->assertJsonPath('groups.0.key', 'timeline')
            ->assertJsonPath('groups.0.items.0.target', 'wedding.toasts')
            ->assertJsonPath('groups.0.items.0.target_label', 'Toasts');

        $applicationId = $this->actingAs($this->owner)
            ->postJson(route('events.questionnaires.route', [$this->event->id, $this->instance->id]), [
                'items' => [['response_id' => $response->id, 'target' => 'wedding.toasts']],
            ])
            ->assertOk()
            ->assertJsonPath('applied', 1)
            ->json('application_id');

        $dances = collect(data_get($this->event->fresh()->additional_data, 'wedding.dances'));
        $this->assertSame('Best man, then maid of honor', data_get($dances->firstWhere('title', 'Toasts'), 'data'));

        $this->actingAs($this->owner)
            ->postJson(route('events.questionnaires.undo', [$this->event->id, $applicationId]))
            ->assertOk()
            ->assertJsonPath('undone', true);

        $dances = collect(data_get($this->event->fresh()->additional_data, 'wedding.dances'));
        $this->assertNull($dances->firstWhere('title', 'Toasts'));
        $this->assertNull($response->fresh()->applied_to_event_at);
    }

    public function test_routing_json_requires_questionnaires_permission(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson(route('events.questionnaires.routing', [$this->event->id, $this->instance->id]))
            ->assertStatus(403);
        $this->actingAs($stranger)
            ->postJson(route('events.questionnaires.route', [$this->event->id, $this->instance->id]), ['items' => []])
            ->assertStatus(403);
    }
}
