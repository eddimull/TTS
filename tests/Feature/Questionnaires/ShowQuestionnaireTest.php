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
 * Covers QuestionnairesController::show, the Inertia "Questionnaires/Show"
 * page. The booking date displayed here is derived from the related events
 * (the `bookings` table has no `date`/`venue_name` columns), and a booking
 * can span multiple events on different dates — so the date is rendered as a
 * comma-separated list. Regression coverage for TTS-BAND-14T.
 */
class ShowQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Questionnaires $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->template = Questionnaires::factory()->create(['band_id' => $this->band->id]);
    }

    private function bookingWithEvents(array $dates): Bookings
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        foreach ($dates as $date) {
            Events::factory()->create([
                'eventable_type' => Bookings::class,
                'eventable_id' => $booking->id,
                'date' => $date,
            ]);
        }
        return $booking;
    }

    private function instanceFor(Bookings $booking): QuestionnaireInstances
    {
        $contact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $booking->contacts()->attach($contact, ['is_primary' => true]);

        return QuestionnaireInstances::factory()->create([
            'questionnaire_id' => $this->template->id,
            'booking_id' => $booking->id,
            'recipient_contact_id' => $contact->id,
            'sent_by_user_id' => $this->owner->id,
        ]);
    }

    public function test_show_renders_single_event_booking_date(): void
    {
        $booking = $this->bookingWithEvents(['2026-07-04']);
        $this->instanceFor($booking);

        $response = $this->actingAs($this->owner)
            ->get(route('questionnaires.show', [$this->band, $this->template]));

        $response->assertOk();
        $response->assertInertia(fn ($a) => $a
            ->component('Questionnaires/Show')
            ->where('instances.0.booking.date', 'Jul 4, 2026'));
    }

    public function test_show_renders_multi_event_booking_dates_comma_separated(): void
    {
        // Created out of chronological order to prove the accessor sorts.
        $booking = $this->bookingWithEvents(['2026-07-06', '2026-07-04', '2026-07-05']);
        $this->instanceFor($booking);

        $response = $this->actingAs($this->owner)
            ->get(route('questionnaires.show', [$this->band, $this->template]));

        $response->assertOk();
        $response->assertInertia(fn ($a) => $a
            ->where('instances.0.booking.date', 'Jul 4, 2026, Jul 5, 2026, Jul 6, 2026'));
    }

    public function test_show_dedupes_events_sharing_a_date(): void
    {
        $booking = $this->bookingWithEvents(['2026-07-04', '2026-07-04']);
        $this->instanceFor($booking);

        $response = $this->actingAs($this->owner)
            ->get(route('questionnaires.show', [$this->band, $this->template]));

        $response->assertOk();
        $response->assertInertia(fn ($a) => $a
            ->where('instances.0.booking.date', 'Jul 4, 2026'));
    }

    public function test_show_handles_booking_with_no_events(): void
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->instanceFor($booking);

        $response = $this->actingAs($this->owner)
            ->get(route('questionnaires.show', [$this->band, $this->template]));

        $response->assertOk();
        $response->assertInertia(fn ($a) => $a
            ->where('instances.0.booking.date', null));
    }

    public function test_send_list_only_includes_bookings_with_upcoming_events(): void
    {
        $upcoming = $this->bookingWithEvents([now()->addWeek()->toDateString()]);
        $past = $this->bookingWithEvents([now()->subWeek()->toDateString()]);
        $eventless = Bookings::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)
            ->get(route('questionnaires.show', [$this->band, $this->template]));

        $response->assertOk();
        $response->assertInertia(function ($a) use ($upcoming, $past, $eventless) {
            $ids = collect($a->toArray()['props']['bookings'])->pluck('id');
            $this->assertTrue($ids->contains($upcoming->id), 'upcoming booking should be listed');
            $this->assertFalse($ids->contains($past->id), 'past-only booking should be excluded');
            $this->assertFalse($ids->contains($eventless->id), 'event-less booking should be excluded');
        });
    }
}
