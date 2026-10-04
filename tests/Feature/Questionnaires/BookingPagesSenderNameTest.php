<?php

namespace Tests\Feature\Questionnaires;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Events;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The booking pages that list a booking's questionnaire instances show who
 * sent each one alongside the sent date, for audit purposes.
 */
class BookingPagesSenderNameTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Bookings $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $this->booking->id,
            'date' => now()->addMonth()->toDateString(),
        ]);
        $contact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $this->booking->contacts()->attach($contact, ['is_primary' => true]);

        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);
        QuestionnaireInstances::factory()->create([
            'questionnaire_id' => $template->id,
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $contact->id,
            'sent_by_user_id' => $this->owner->id,
        ]);
    }

    public function test_booking_show_includes_sender_name(): void
    {
        $this->actingAs($this->owner)
            ->get("/bands/{$this->band->id}/booking/{$this->booking->id}")
            ->assertOk()
            ->assertInertia(fn ($a) => $a
                ->component('Bookings/Show')
                ->where('questionnaireInstances.0.sent_by_name', $this->owner->name));
    }

    public function test_booking_events_includes_sender_name(): void
    {
        $this->actingAs($this->owner)
            ->get("/bands/{$this->band->id}/booking/{$this->booking->id}/events")
            ->assertOk()
            ->assertInertia(fn ($a) => $a
                ->component('Bookings/Events')
                ->where('events.0.questionnaire_instances.0.sent_by_name', $this->owner->name));
    }
}
