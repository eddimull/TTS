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

        $this->band = Bands::factory()->create();
        $this->eventType = EventTypes::factory()->create();
        $this->booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status' => 'confirmed',
        ]);
    }

    private function makeBookingEvent(array $attributes = []): Events
    {
        return Events::factory()->create(array_merge([
            'eventable_id' => $this->booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => '2026-10-10',
        ], $attributes));
    }

    public function test_updating_booking_event_date_dispatches_booking_resync()
    {
        $event = $this->makeBookingEvent();

        Bus::fake();

        $event->update(['date' => '2026-10-20']);

        Bus::assertDispatched(ProcessBookingUpdated::class, function ($job) {
            return $job->uniqueId() === 'booking-updated-' . $this->booking->id;
        });
    }

    public function test_creating_booking_event_dispatches_booking_resync()
    {
        Bus::fake();

        $this->makeBookingEvent();

        Bus::assertDispatched(ProcessBookingUpdated::class, function ($job) {
            return $job->uniqueId() === 'booking-updated-' . $this->booking->id;
        });
    }

    public function test_deleting_one_of_several_booking_events_dispatches_booking_resync()
    {
        $event = $this->makeBookingEvent();
        $this->makeBookingEvent(['date' => '2026-10-11']);

        Bus::fake();

        $event->delete();

        Bus::assertDispatched(ProcessBookingUpdated::class, function ($job) {
            return $job->uniqueId() === 'booking-updated-' . $this->booking->id;
        });
    }

    public function test_deleting_the_last_booking_event_does_not_dispatch_booking_resync()
    {
        // With no events left there are no dates to derive the booking's
        // calendar entry from; a resync would push null start/end to Google.
        $event = $this->makeBookingEvent();

        Bus::fake();

        $event->delete();

        Bus::assertNotDispatched(ProcessBookingUpdated::class);
    }

    public function test_rehearsal_event_update_does_not_dispatch_booking_resync()
    {
        $rehearsal = Rehearsal::factory()->create(['band_id' => $this->band->id]);
        $event = Events::factory()->create([
            'eventable_id' => $rehearsal->id,
            'eventable_type' => Rehearsal::class,
            'event_type_id' => $this->eventType->id,
            'date' => '2026-10-10',
        ]);

        Bus::fake();

        $event->update(['date' => '2026-10-20']);

        Bus::assertNotDispatched(ProcessBookingUpdated::class);
    }
}
