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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Bus;
use Google\Service\Calendar\Event as GoogleEvent;
use Mockery;

class BookingCreationPreventsDuplicateCalendarEventsTest extends TestCase
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

        // Create event type
        $this->eventType = EventTypes::factory()->create();

        $this->actingAs($this->user);
    }


    public function test_booking_creation_creates_exactly_one_calendar_event_per_calendar_type()
    {
        $insertEventCalls = [
            'booking' => 0,
            'event' => 0,
            'public' => 0,
        ];

        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        $mockService->shouldReceive('insertEvent')
            ->with($this->bookingCalendar->calendar_id, Mockery::any())
            ->times(1)
            ->andReturnUsing(function () use (&$insertEventCalls) {
                $insertEventCalls['booking']++;
                $event = new GoogleEvent();
                $event->setId('booking-event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('insertEvent')
            ->with($this->eventCalendar->calendar_id, Mockery::any())
            ->times(1)
            ->andReturnUsing(function () use (&$insertEventCalls) {
                $insertEventCalls['event']++;
                $event = new GoogleEvent();
                $event->setId('event-event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('insertEvent')
            ->with($this->publicCalendar->calendar_id, Mockery::any())
            ->times(1)
            ->andReturnUsing(function () use (&$insertEventCalls) {
                $insertEventCalls['public']++;
                $event = new GoogleEvent();
                $event->setId('public-event-' . uniqid());
                return $event;
            });

        $mockService->shouldNotReceive('updateEvent');

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status' => 'confirmed',
            'date' => now()->addDays(10),
            'event_type_id' => $this->eventType->id,
            'price' => 1000,
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
            'additional_data' => (object)['public' => true],
        ]);


        $totalGoogleEventsCount = GoogleEvents::whereIn('google_eventable_id', [$booking->id, $event->id])
            ->count();

        $this->assertEquals(
            3,
            $totalGoogleEventsCount,
            "Expected exactly 3 GoogleEvents records total (1 booking, 1 event, 1 public), found {$totalGoogleEventsCount}"
        );


        $bookingCalendarEvent = GoogleEvents::where('google_eventable_id', $booking->id)
            ->where('google_eventable_type', Bookings::class)
            ->where('band_calendar_id', $this->bookingCalendar->id)
            ->count();
        $this->assertEquals(1, $bookingCalendarEvent, 'Booking calendar should have exactly 1 event');

        $eventCalendarEvent = GoogleEvents::where('google_eventable_id', $event->id)
            ->where('google_eventable_type', Events::class)
            ->where('band_calendar_id', $this->eventCalendar->id)
            ->count();
        $this->assertEquals(1, $eventCalendarEvent, 'Event calendar should have exactly 1 event');

        $publicCalendarEvent = GoogleEvents::where('google_eventable_id', $event->id)
            ->where('google_eventable_type', Events::class)
            ->where('band_calendar_id', $this->publicCalendar->id)
            ->count();
        $this->assertEquals(1, $publicCalendarEvent, 'Public calendar should have exactly 1 event');

        $this->assertEquals(
            1,
            $insertEventCalls['booking'],
            "insertEvent should be called exactly once for booking calendar, called {$insertEventCalls['booking']} times"
        );
        $this->assertEquals(
            1,
            $insertEventCalls['event'],
            "insertEvent should be called exactly once for event calendar, called {$insertEventCalls['event']} times"
        );
        $this->assertEquals(
            1,
            $insertEventCalls['public'],
            "insertEvent should be called exactly once for public calendar, called {$insertEventCalls['public']} times"
        );
    }

    public function test_editing_event_after_creation_updates_not_inserts()
    {
        
        $updateCalls = 0;

        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
            'event_type_id' => $this->eventType->id,
        ]);

        $event = Events::withoutEvents(function () use ($booking) {
            return Events::factory()->create([
                'eventable_id' => $booking->id,
                'eventable_type' => Bookings::class,
                'event_type_id' => $this->eventType->id,
                'date' => $booking->date,
                'additional_data' => (object)['public' => true],
            ]);
        });

       
        GoogleEvents::create([
            'google_eventable_id' => $booking->id,
            'google_eventable_type' => Bookings::class,
            'band_calendar_id' => $this->bookingCalendar->id,
            'google_event_id' => 'booking-123',
        ]);

        GoogleEvents::create([
            'google_eventable_id' => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id' => $this->eventCalendar->id,
            'google_event_id' => 'event-456',
        ]);

        GoogleEvents::create([
            'google_eventable_id' => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id' => $this->publicCalendar->id,
            'google_event_id' => 'public-789',
        ]);

    
        $mockService->shouldReceive('updateEvent')
            ->with($this->eventCalendar->calendar_id, 'event-456', Mockery::any())
            ->once()
            ->andReturnUsing(function () use (&$updateCalls) {
                $updateCalls++;
                $event = new GoogleEvent();
                $event->setId('event-456');
                return $event;
            });

        $mockService->shouldReceive('updateEvent')
            ->with($this->publicCalendar->calendar_id, 'public-789', Mockery::any())
            ->once()
            ->andReturnUsing(function () use (&$updateCalls) {
                $updateCalls++;
                $event = new GoogleEvent();
                $event->setId('public-789');
                return $event;
            });


        $mockService->shouldNotReceive('insertEvent');


        $event->title = 'Updated Title';
        $event->save();

        $this->assertGreaterThan(0, $updateCalls, 'updateEvent should have been called');

        $this->assertCount(
            3,
            GoogleEvents::where(function ($query) use ($event, $booking) {
                $query->where('google_eventable_id', $event->id)
                    ->where('google_eventable_type', Events::class);
            })->orWhere(function ($query) use ($booking) {
                $query->where('google_eventable_id', $booking->id)
                    ->where('google_eventable_type', Bookings::class);
            })->get()
        );
    }

    public function test_multiple_rapid_saves_do_not_create_duplicates()
    {
        $insertCounts = [
            'booking' => 0,
            'event' => 0,
        ];

        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        $mockService->shouldReceive('insertEvent')
            ->with($this->bookingCalendar->calendar_id, Mockery::any())
            ->once()
            ->andReturnUsing(function () use (&$insertCounts) {
                $insertCounts['booking']++;
                $event = new GoogleEvent();
                $event->setId('booking-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('insertEvent')
            ->with($this->eventCalendar->calendar_id, Mockery::any())
            ->once()
            ->andReturnUsing(function () use (&$insertCounts) {
                $insertCounts['event']++;
                $event = new GoogleEvent();
                $event->setId('event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('updateEvent')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($calendarId, $eventId) {
                $event = new GoogleEvent();
                $event->setId($eventId);
                return $event;
            });

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
            'event_type_id' => $this->eventType->id,
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
        ]);

        $this->assertEquals(1, $insertCounts['booking'], "Booking calendar should have 1 insert");
        $this->assertEquals(1, $insertCounts['event'], "Event calendar should have 1 insert");

        $initialInsertTotal = $insertCounts['booking'] + $insertCounts['event'];

        for ($i = 0; $i < 3; $i++) {
            $event->value = 100 * ($i + 1);
            $event->saveQuietly(); 
        }


        $finalInsertTotal = $insertCounts['booking'] + $insertCounts['event'];
        $this->assertEquals(
            $initialInsertTotal,
            $finalInsertTotal,
            "Multiple saveQuietly calls should not trigger additional inserts"
        );

        $totalRecords = GoogleEvents::whereIn('google_eventable_id', [$booking->id, $event->id])->count();
        $this->assertEquals(
            2,
            $totalRecords,
            "Should have exactly 2 GoogleEvents records (1 booking, 1 event)"
        );
    }

    public function test_concurrent_event_updates_preserve_latest_changes()
    {
        $apiCalls = [];

        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        $mockService->shouldReceive('insertEvent')
            ->andReturnUsing(function ($calendarId, $eventData) use (&$apiCalls) {
                $apiCalls[] = [
                    'type' => 'insert',
                    'calendar' => $calendarId,
                    'summary' => $eventData->getSummary(),
                    'timestamp' => microtime(true),
                ];
                $event = new GoogleEvent();
                $event->setId('event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('updateEvent')
            ->andReturnUsing(function ($calendarId, $eventId, $eventData) use (&$apiCalls) {
                $apiCalls[] = [
                    'type' => 'update',
                    'calendar' => $calendarId,
                    'event_id' => $eventId,
                    'summary' => $eventData->getSummary(),
                    'timestamp' => microtime(true),
                ];
                $event = new GoogleEvent();
                $event->setId($eventId);
                return $event;
            });

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
            'event_type_id' => $this->eventType->id,
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
            'title' => 'Original Event Title',
        ]);

        $event->title = 'Updated Event Title';
        $event->save();

        $event->title = 'Final Event Title';
        $event->save();


        $eventCalendarCalls = array_filter($apiCalls, function ($call) {
            return $call['calendar'] === $this->eventCalendar->calendar_id;
        });

        $this->assertNotEmpty($eventCalendarCalls, 'Should have made at least one API call to event calendar');

        $lastCall = end($eventCalendarCalls);

        $this->assertEquals(
            'Final Event Title',
            $lastCall['summary'],
            'Google Calendar should have the FINAL event title, not an intermediate one. This ensures jobs fetch fresh data from DB.'
        );

        $event->refresh();
        $this->assertEquals('Final Event Title', $event->title);


        $googleEventsCount = GoogleEvents::where('google_eventable_id', $event->id)
            ->where('band_calendar_id', $this->eventCalendar->id)
            ->count();

        $this->assertEquals(
            1,
            $googleEventsCount,
            'Should have exactly 1 GoogleEvents record despite multiple rapid updates'
        );
    }

    public function test_job_uniqueness_prevents_stale_data_sync()
    {
        $processedEvents = [];

        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);


        $mockService->shouldReceive('insertEvent')
            ->andReturnUsing(function ($calendarId, $eventData) use (&$processedEvents) {
                $processedEvents[] = $eventData->getSummary();
                $event = new GoogleEvent();
                $event->setId('event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('updateEvent')
            ->andReturnUsing(function ($calendarId, $eventId, $eventData) use (&$processedEvents) {
                $processedEvents[] = $eventData->getSummary();
                $event = new GoogleEvent();
                $event->setId($eventId);
                return $event;
            });

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
            'event_type_id' => $this->eventType->id,
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
            'title' => 'Version 1',
        ]);

        $event->title = 'Version 2';
        $event->save();

        $event->title = 'Version 3';
        $event->save();

        $event->title = 'Version 4';
        $event->save();


        $event->refresh();
        $this->assertEquals('Version 4', $event->title, 'Event model should have the latest title');


        $this->assertContains('Version 1', $processedEvents, 'Initial version should be synced');

        // Verify only ONE GoogleEvent record exists (no duplicates despite multiple saves)
        $googleEventsCount = GoogleEvents::where('google_eventable_id', $event->id)
            ->where('band_calendar_id', $this->eventCalendar->id)
            ->count();

        $this->assertEquals(
            1,
            $googleEventsCount,
            'Should have exactly 1 GoogleEvents record despite multiple rapid updates'
        );
    }


    public function test_job_fetches_fresh_event_data_from_database()
    {
        $syncedTitles = [];

        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);


        $mockService->shouldReceive('insertEvent')
            ->andReturnUsing(function ($calendarId, $eventData) use (&$syncedTitles) {
                $syncedTitles[] = [
                    'calendar' => $calendarId,
                    'title' => $eventData->getSummary(),
                ];
                $event = new GoogleEvent();
                $event->setId('event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('updateEvent')
            ->andReturnUsing(function ($calendarId, $eventId, $eventData) use (&$syncedTitles) {
                $syncedTitles[] = [
                    'calendar' => $calendarId,
                    'title' => $eventData->getSummary(),
                ];
                $event = new GoogleEvent();
                $event->setId($eventId);
                return $event;
            });

        
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
            'event_type_id' => $this->eventType->id,
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
            'title' => 'Initial Title',
        ]);

        
        $this->assertNotEmpty($syncedTitles, 'Should have synced at least one event');

        
        $initialSynced = collect($syncedTitles)->firstWhere('title', 'Initial Title');
        $this->assertNotNull($initialSynced, 'Initial title should have been synced to Google Calendar');


        $bookingSynced = collect($syncedTitles)->firstWhere('calendar', $this->bookingCalendar->calendar_id);
        $this->assertNotNull($bookingSynced, 'Booking should have been synced to booking calendar');

        $eventSynced = collect($syncedTitles)->firstWhere('calendar', $this->eventCalendar->calendar_id);
        $this->assertNotNull($eventSynced, 'Event should have been synced to event calendar');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
