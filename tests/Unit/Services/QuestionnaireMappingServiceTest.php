<?php

namespace Tests\Unit\Services;

use App\Models\Bookings;
use App\Models\Events;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\User;
use App\Services\QuestionnaireMappingRegistry;
use App\Services\QuestionnaireMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuestionnaireMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuestionnaireMappingService(new QuestionnaireMappingRegistry());
    }

    private function makeInstanceWithEvent(): array
    {
        $booking = Bookings::factory()->create();
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'additional_data' => ['wedding' => ['onsite' => 0, 'dances' => [
                ['title' => 'First Dance', 'data' => 'TBD'],
                ['title' => 'Father Daughter', 'data' => 'TBD'],
            ]]],
        ]);

        $instance = QuestionnaireInstances::factory()->create(['booking_id' => $booking->id]);

        return [$instance, $event, $booking];
    }

    public function test_apply_response_writes_yes_no_answer_to_event_additional_data(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);
        $user = User::factory()->create();

        $this->service->applyResponse($response, $user);

        $event->refresh();
        $this->assertSame(true, data_get($event->additional_data, 'wedding.onsite'));
        $response->refresh();
        $this->assertNotNull($response->applied_to_event_at);
        $this->assertSame($user->id, $response->applied_by_user_id);
    }

    public function test_apply_response_writes_outside_path(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.outside',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);

        $this->service->applyResponse($response, User::factory()->create());

        $event->refresh();
        $this->assertSame(true, data_get($event->additional_data, 'outside'));
    }

    public function test_apply_response_updates_first_dance_entry(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'mapping_target' => 'wedding.dance.first',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Evergreen — Yebba',
        ]);

        $this->service->applyResponse($response, User::factory()->create());

        $event->refresh();
        $dances = data_get($event->additional_data, 'wedding.dances');
        $this->assertSame('Evergreen — Yebba',
            data_get(collect($dances)->firstWhere('title', 'First Dance'), 'data')
        );
    }

    public function test_apply_response_inserts_dance_entry_when_missing(): void
    {
        $booking = Bookings::factory()->create();
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'additional_data' => ['wedding' => ['dances' => []]],
        ]);

        $instance = QuestionnaireInstances::factory()->create(['booking_id' => $booking->id]);
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'mapping_target' => 'wedding.dance.money',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Gold Digger',
        ]);

        $this->service->applyResponse($response, User::factory()->create());

        $event->refresh();
        $dances = data_get($event->additional_data, 'wedding.dances');
        $this->assertSame('Gold Digger',
            data_get(collect($dances)->firstWhere('title', 'Money Dance'), 'data')
        );
    }

    public function test_apply_response_throws_when_field_has_no_mapping_target(): void
    {
        [$instance] = $this->makeInstanceWithEvent();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'mapping_target' => null,
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'X',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->applyResponse($response, User::factory()->create());
    }

    public function test_apply_response_throws_when_booking_has_no_event(): void
    {
        $booking = Bookings::factory()->create();
        $instance = QuestionnaireInstances::factory()->create(['booking_id' => $booking->id]);
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->applyResponse($response, User::factory()->create());
    }

    public function test_append_all_to_notes_appends_block_with_timestamp(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();
        $event->update(['notes' => '<p>existing notes</p>']);

        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'label' => "Bride's Name",
            'position' => 10,
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Jane',
        ]);

        $this->service->appendAllToNotes($instance, User::factory()->create());

        $event->refresh();
        $this->assertStringContainsString('existing notes', $event->notes);
        $this->assertStringContainsString("Bride's Name", $event->notes);
        $this->assertStringContainsString('Jane', $event->notes);
        $this->assertStringContainsString($instance->name, $event->notes);
    }

    public function test_append_all_to_notes_skips_instructions_and_renders_headers_as_h4(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();

        QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'header',
            'label' => 'Bride and Groom',
            'position' => 10,
        ]);
        QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'instructions',
            'label' => 'Some helper text',
            'position' => 20,
        ]);
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'label' => 'Name',
            'position' => 30,
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Jane',
        ]);

        $this->service->appendAllToNotes($instance, User::factory()->create());

        $event->refresh();
        $this->assertStringContainsString('<h4>Bride and Groom</h4>', $event->notes);
        $this->assertStringNotContainsString('Some helper text', $event->notes);
    }
}
