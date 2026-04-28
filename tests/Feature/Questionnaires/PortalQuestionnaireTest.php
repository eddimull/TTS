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
}
