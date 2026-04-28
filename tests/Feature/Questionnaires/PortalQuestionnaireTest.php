<?php

namespace Tests\Feature\Questionnaires;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireInstanceFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private Bookings $booking;
    private Contacts $contact;
    private QuestionnaireInstances $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->contact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $this->booking->contacts()->attach($this->contact, ['is_primary' => true]);

        $this->instance = QuestionnaireInstances::factory()->create([
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
        ]);

        QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'short_text',
            'label' => 'Bride Name',
            'position' => 10,
        ]);
    }

    public function test_contact_can_view_questionnaire_via_portal(): void
    {
        $response = $this->actingAs($this->contact, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $response->assertStatus(200);
        $response->assertInertia(fn ($a) => $a
            ->component('Contact/Questionnaire/Show')
            ->has('instance')
            ->has('fields', 1));
    }

    public function test_non_booking_contact_cannot_view_questionnaire(): void
    {
        $other = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);

        $response = $this->actingAs($other, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $response->assertStatus(403);
    }

    public function test_first_open_stamps_first_opened_at(): void
    {
        $this->assertNull($this->instance->first_opened_at);

        $this->actingAs($this->contact, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $this->instance->refresh();
        $this->assertNotNull($this->instance->first_opened_at);
    }

    public function test_first_opened_at_is_not_overwritten_on_subsequent_views(): void
    {
        $original = now()->subHour();
        $this->instance->update(['first_opened_at' => $original]);

        $this->actingAs($this->contact, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $this->instance->refresh();
        $this->assertEquals($original->timestamp, $this->instance->first_opened_at->timestamp);
    }

    public function test_other_contact_on_booking_can_also_view(): void
    {
        $partner = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $this->booking->contacts()->attach($partner);

        $response = $this->actingAs($partner, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $response->assertStatus(200);
    }

    public function test_response_save_upserts_response_row(): void
    {
        $field = $this->instance->fields()->first();

        $response = $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'Jane Smith']
            );

        $response->assertStatus(200);
        $this->assertDatabaseHas('questionnaire_responses', [
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Jane Smith',
        ]);

        // Second save with new value upserts (does not duplicate)
        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'Jane Doe']
            );

        $this->assertSame(
            1,
            \App\Models\QuestionnaireResponses::where('instance_field_id', $field->id)->count()
        );
        $this->assertDatabaseHas('questionnaire_responses', [
            'instance_field_id' => $field->id,
            'value' => 'Jane Doe',
        ]);
    }

    public function test_response_save_transitions_status_from_sent_to_in_progress(): void
    {
        $this->assertSame('sent', $this->instance->status);
        $field = $this->instance->fields()->first();

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'X']
            )
            ->assertStatus(200);

        $this->instance->refresh();
        $this->assertSame('in_progress', $this->instance->status);
    }

    public function test_response_save_does_not_change_status_when_already_submitted(): void
    {
        $this->instance->update(['status' => 'submitted', 'submitted_at' => now()]);
        $field = $this->instance->fields()->first();

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'updated']
            )
            ->assertStatus(200);

        $this->instance->refresh();
        $this->assertSame('submitted', $this->instance->status);
    }

    public function test_response_save_blocked_when_locked(): void
    {
        $this->instance->update(['status' => 'locked', 'locked_at' => now()]);
        $field = $this->instance->fields()->first();

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'X']
            )
            ->assertStatus(403);
    }

    public function test_response_save_rejects_field_from_different_instance(): void
    {
        $otherInstance = QuestionnaireInstances::factory()->create();
        $foreignField = QuestionnaireInstanceFields::factory()->create(['instance_id' => $otherInstance->id]);

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $foreignField->id, 'value' => 'X']
            )
            ->assertStatus(422);
    }

    public function test_response_save_encodes_array_for_multi_value_field(): void
    {
        $multiField = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'multi_select',
            'position' => 20,
        ]);

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $multiField->id, 'value' => ['rock', 'jazz']]
            )
            ->assertStatus(200);

        $stored = \App\Models\QuestionnaireResponses::where('instance_field_id', $multiField->id)->first();
        $this->assertSame(['rock', 'jazz'], json_decode($stored->value, true));
    }
}
