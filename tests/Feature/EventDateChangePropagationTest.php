<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\Rehearsal;
use App\Models\EventTypes;
use App\Jobs\ProcessBookingUpdated;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EventDateChangePropagationTest extends TestCase
{
    use RefreshDatabase;

    protected Bands $band;
    protected Bookings $booking;
    protected EventTypes $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake before any factories so observer-dispatched jobs never run for
        // real. Arrange-phase events are created with withoutEvents() below:
        // a faked ShouldBeUniqueUntilProcessing job never "processes", so a
        // recorded arrange-phase dispatch would hold the unique lock and
        // silently swallow the act-phase dispatch under assertion.
        Bus::fake();

        $this->band = Bands::factory()->create();
        $this->eventType = EventTypes::factory()->create();
        $this->booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status' => 'confirmed',
        ]);
    }

    private function bookingEventAttributes(array $attributes = []): array
    {
        return array_merge([
            'eventable_id' => $this->booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => '2026-10-10',
        ], $attributes);
    }

    private function makeBookingEventQuietly(array $attributes = []): Events
    {
        return Events::withoutEvents(
            fn () => Events::factory()->create($this->bookingEventAttributes($attributes))
        );
    }

    private function assertBookingResyncDispatched(): void
    {
        Bus::assertDispatched(ProcessBookingUpdated::class, function ($job) {
            return $job->uniqueId() === 'booking-updated-' . $this->booking->id;
        });
    }

    public function test_updating_booking_event_date_dispatches_booking_resync()
    {
        $event = $this->makeBookingEventQuietly();

        $event->update(['date' => '2026-10-20']);

        $this->assertBookingResyncDispatched();
    }

    public function test_creating_booking_event_dispatches_booking_resync()
    {
        Events::factory()->create($this->bookingEventAttributes());

        $this->assertBookingResyncDispatched();
    }

    public function test_deleting_one_of_several_booking_events_dispatches_booking_resync()
    {
        $event = $this->makeBookingEventQuietly();
        $this->makeBookingEventQuietly(['date' => '2026-10-11']);

        $event->delete();

        $this->assertBookingResyncDispatched();
    }

    public function test_deleting_the_last_booking_event_does_not_dispatch_booking_resync()
    {
        // With no events left there are no dates to derive the booking's
        // calendar entry from; a resync would push null start/end to Google.
        $event = $this->makeBookingEventQuietly();

        $event->delete();

        Bus::assertNotDispatched(ProcessBookingUpdated::class);
    }

    public function test_rehearsal_event_update_does_not_dispatch_booking_resync()
    {
        $rehearsal = Rehearsal::factory()->create(['band_id' => $this->band->id]);
        $event = Events::withoutEvents(fn () => Events::factory()->create([
            'eventable_id' => $rehearsal->id,
            'eventable_type' => Rehearsal::class,
            'event_type_id' => $this->eventType->id,
            'date' => '2026-10-10',
        ]));

        $event->update(['date' => '2026-10-20']);

        Bus::assertNotDispatched(ProcessBookingUpdated::class);
    }
}
