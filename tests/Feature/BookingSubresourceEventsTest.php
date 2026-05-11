<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Feature coverage for the web booking → events subresource endpoints
 * (Update Booking Event / Delete Booking Event) and for the booking PATCH
 * endpoint's prohibited-field guard.
 */
class BookingSubresourceEventsTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Bookings $booking;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the bus before any factory creates run. EventObserver and
        // BookingObserver dispatch ShouldBeUniqueUntilProcessing jobs whose
        // file-cache-backed unique locks can race with other suite tests that
        // share auto-incremented IDs after RefreshDatabase rolls back.
        // We don't assert on these jobs here, so faking is safe.
        Bus::fake();

        $this->band    = Bands::factory()->create();
        $this->owner   = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $this->booking->id,
            'date'           => now()->addDays(30)->format('Y-m-d'),
        ]);
    }

    /**
     * UpdateBookingEventRequest requires a structured additional_data payload.
     * Build the minimum-viable shape the request validator accepts.
     */
    private function eventPayload(array $overrides = []): array
    {
        $base = [
            'title'         => 'New Event Title',
            'date'          => now()->addDays(45)->format('Y-m-d'),
            'start_time'    => '19:00',
            'end_time'      => '22:00',
            'venue_name'    => 'The Venue',
            'venue_address' => '123 Stage Ln',
            'price'         => 1500,
            'notes'         => 'Some notes',
            'additional_data' => [
                'public'             => true,
                'outside'            => false,
                'lodging'            => ['needed' => false],
                'production_needed'  => false,
                'backline_provided'  => false,
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }

    public function test_owner_can_create_event_via_subresource_endpoint(): void
    {
        $initialCount = Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $this->booking->id)->count();

        $response = $this->actingAs($this->owner)->post(
            route('Update Booking Event', [$this->band, $this->booking]),
            $this->eventPayload(['title' => 'Subresource Created']),
        );

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Event Created');

        $afterCount = Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $this->booking->id)->count();
        $this->assertSame($initialCount + 1, $afterCount);
        $this->assertDatabaseHas('events', [
            'eventable_type' => Bookings::class,
            'eventable_id'   => $this->booking->id,
            'title'          => 'Subresource Created',
        ]);
    }

    public function test_owner_can_update_existing_event_via_subresource_endpoint(): void
    {
        $event = $this->booking->events()->first();

        $response = $this->actingAs($this->owner)->put(
            route('Update Booking Event', [$this->band, $this->booking, $event]),
            $this->eventPayload(['title' => 'Renamed Event', 'venue_name' => 'New Venue']),
        );

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Event Updated');

        $this->assertDatabaseHas('events', [
            'id'         => $event->id,
            'title'      => 'Renamed Event',
            'venue_name' => 'New Venue',
        ]);
    }

    public function test_deleting_the_last_event_returns_an_error_and_keeps_the_event(): void
    {
        $event = $this->booking->events()->first();
        $count = Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $this->booking->id)->count();
        $this->assertSame(1, $count);

        $response = $this->actingAs($this->owner)->delete(
            route('Delete Booking Event', [$this->band, $this->booking, $event]),
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('event');

        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $countAfter = Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $this->booking->id)->count();
        $this->assertSame(1, $countAfter);
    }

    public function test_deleting_a_non_last_event_succeeds_and_redistributes_values(): void
    {
        // Ensure the booking has a known price so redistribution math is deterministic.
        $this->booking->update(['price' => 600]);

        $second = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $this->booking->id,
            'date'           => now()->addDays(31)->format('Y-m-d'),
        ]);
        $third = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $this->booking->id,
            'date'           => now()->addDays(32)->format('Y-m-d'),
        ]);

        $countBefore = Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $this->booking->id)
            ->count();
        $this->assertSame(3, $countBefore);

        $response = $this->actingAs($this->owner)->delete(
            route('Delete Booking Event', [$this->band, $this->booking, $third]),
        );

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Event Deleted');

        $this->assertDatabaseMissing('events', ['id' => $third->id]);

        $countAfter = Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $this->booking->id)
            ->count();
        $this->assertSame(2, $countAfter);

        // Remaining events should have had their value redistributed.
        foreach (Events::where('eventable_type', Bookings::class)->where('eventable_id', $this->booking->id)->get() as $e) {
            $this->assertNotNull($e->value);
        }
    }

    public function test_booking_patch_with_date_field_returns_422(): void
    {
        $payload = collect($this->booking->fresh()->toArray())->except([
            'start_date', 'end_date', 'event_count', 'venue_summary',
            'is_multi_event', 'total_duration', 'events',
        ])->toArray();
        $payload['date'] = now()->addDays(60)->format('Y-m-d');

        $response = $this->actingAs($this->owner)->put(
            route('bands.booking.update', [$this->band, $this->booking]),
            $payload,
        );

        $response->assertSessionHasErrors('date');
    }

    public function test_booking_patch_with_venue_name_field_returns_422(): void
    {
        $payload = collect($this->booking->fresh()->toArray())->except([
            'start_date', 'end_date', 'event_count', 'venue_summary',
            'is_multi_event', 'total_duration', 'events',
        ])->toArray();
        $payload['venue_name'] = 'Some Venue';

        $response = $this->actingAs($this->owner)->put(
            route('bands.booking.update', [$this->band, $this->booking]),
            $payload,
        );

        $response->assertSessionHasErrors('venue_name');
    }
}
