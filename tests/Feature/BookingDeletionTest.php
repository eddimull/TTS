<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\EventTypes;
use App\Models\GoogleEvents;
use App\Models\BandCalendars;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class BookingDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Bands $band;
    protected BandCalendars $bookingCalendar;
    protected BandCalendars $eventCalendar;
    protected BandCalendars $publicCalendar;
    protected EventTypes $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'sync']);

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();

        $this->bookingCalendar = BandCalendars::factory()->create([
            'band_id' => $this->band->id,
            'type' => 'booking',
            'calendar_id' => 'test-booking-calendar-id'
        ]);

        $this->eventCalendar = BandCalendars::factory()->create([
            'band_id' => $this->band->id,
            'type' => 'event',
            'calendar_id' => 'test-event-calendar-id'
        ]);

        $this->publicCalendar = BandCalendars::factory()->create([
            'band_id' => $this->band->id,
            'type' => 'public',
            'calendar_id' => 'test-public-calendar-id'
        ]);

        $this->eventType = EventTypes::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_booking_deletion_removes_associated_events_and_calendar_entries()
    {
        $deletedEvents = [];

        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);


        $mockService->shouldReceive('insertEvent')
            ->andReturnUsing(function ($calendarId, $eventData) {
                $event = new \Google\Service\Calendar\Event();
                $event->setId('gcal-' . uniqid());
                return $event;
            });

        // Track deletions
        $mockService->shouldReceive('deleteEvent')
            ->andReturnUsing(function ($calendarId, $eventId) use (&$deletedEvents) {
                $deletedEvents[] = [
                    'calendar_id' => $calendarId,
                    'event_id' => $eventId,
                ];
                return true;
            });

        $booking = Bookings::withoutEvents(function () {
            return Bookings::factory()->create([
                'band_id' => $this->band->id,
                'date' => now()->addDays(10),
                'event_type_id' => $this->eventType->id,
            ]);
        });

        $event1 = Events::withoutEvents(function () use ($booking) {
            return Events::factory()->create([
                'eventable_id' => $booking->id,
                'eventable_type' => Bookings::class,
                'event_type_id' => $this->eventType->id,
                'date' => $booking->date,
                'title' => 'Event 1',
            ]);
        });

        $event2 = Events::withoutEvents(function () use ($booking) {
            return Events::factory()->create([
                'eventable_id' => $booking->id,
                'eventable_type' => Bookings::class,
                'event_type_id' => $this->eventType->id,
                'date' => $booking->date->addDay(),
                'title' => 'Event 2',
                'additional_data' => (object)['public' => true], // This one is public
            ]);
        });

        GoogleEvents::create([
            'google_eventable_id' => $booking->id,
            'google_eventable_type' => Bookings::class,
            'band_calendar_id' => $this->bookingCalendar->id,
            'google_event_id' => 'booking-gcal-123',
        ]);

        GoogleEvents::create([
            'google_eventable_id' => $event1->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id' => $this->eventCalendar->id,
            'google_event_id' => 'event1-gcal-456',
        ]);

        GoogleEvents::create([
            'google_eventable_id' => $event2->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id' => $this->eventCalendar->id,
            'google_event_id' => 'event2-gcal-789',
        ]);

        GoogleEvents::create([
            'google_eventable_id' => $event2->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id' => $this->publicCalendar->id,
            'google_event_id' => 'event2-public-gcal-abc',
        ]);

        $this->assertCount(2, $booking->events, 'Booking should have 2 events');
        $this->assertDatabaseHas('events', ['id' => $event1->id]);
        $this->assertDatabaseHas('events', ['id' => $event2->id]);
        $this->assertCount(4, GoogleEvents::all(), 'Should have 4 GoogleEvents records');


        $booking->delete();

        $this->assertDatabaseMissing('events', ['id' => $event1->id]);
        $this->assertDatabaseMissing('events', ['id' => $event2->id]);


        $remainingGoogleEvents = GoogleEvents::whereIn('google_eventable_id', [$event1->id, $event2->id])
            ->where('google_eventable_type', Events::class)
            ->count();
        $this->assertEquals(0, $remainingGoogleEvents, 'All event GoogleEvents records should be deleted');

        $this->assertGreaterThanOrEqual(3, count($deletedEvents), 'Should have deleted at least 3 calendar events');


        $eventCalendarDeletions = array_filter($deletedEvents, fn($d) => $d['calendar_id'] === $this->eventCalendar->calendar_id);
        $this->assertGreaterThanOrEqual(2, count($eventCalendarDeletions), 'Should have deleted 2 events from event calendar');


        $publicCalendarDeletions = array_filter($deletedEvents, fn($d) => $d['calendar_id'] === $this->publicCalendar->calendar_id);
        $this->assertGreaterThanOrEqual(1, count($publicCalendarDeletions), 'Should have deleted 1 event from public calendar');

        $bookingCalendarDeletions = array_filter($deletedEvents, fn($d) => $d['calendar_id'] === $this->bookingCalendar->calendar_id);
        $this->assertGreaterThanOrEqual(1, count($bookingCalendarDeletions), 'Should have deleted booking from booking calendar');
    }

    public function test_booking_deletion_with_single_event()
    {
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        $mockService->shouldReceive('insertEvent')->andReturn(new \Google\Service\Calendar\Event());
        $mockService->shouldReceive('deleteEvent')->andReturn(true);

        $booking = Bookings::withoutEvents(function () {
            return Bookings::factory()->create([
                'band_id' => $this->band->id,
                'date' => now()->addDays(10),
                'event_type_id' => $this->eventType->id,
            ]);
        });

        $event = Events::withoutEvents(function () use ($booking) {
            return Events::factory()->create([
                'eventable_id' => $booking->id,
                'eventable_type' => Bookings::class,
                'event_type_id' => $this->eventType->id,
                'date' => $booking->date,
            ]);
        });

        $eventId = $event->id;

        $booking->delete();

        $this->assertDatabaseMissing('events', ['id' => $eventId]);
    }

    public function test_booking_deletion_with_no_events()
    {
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        $mockService->shouldReceive('insertEvent')->andReturn(new \Google\Service\Calendar\Event());
        $mockService->shouldReceive('deleteEvent')->andReturn(true);

        $booking = Bookings::withoutEvents(function () {
            return Bookings::factory()->create([
                'band_id' => $this->band->id,
                'date' => now()->addDays(10),
                'event_type_id' => $this->eventType->id,
            ]);
        });

        $bookingId = $booking->id;
        $booking->delete();

        $this->assertDatabaseMissing('bookings', ['id' => $bookingId]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
