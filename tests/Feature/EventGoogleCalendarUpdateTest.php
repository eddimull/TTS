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
use Google\Service\Calendar\Event as GoogleEvent;
use Mockery;

class EventGoogleCalendarUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Bands $band;
    protected BandCalendars $eventCalendar;
    protected BandCalendars $publicCalendar;
    protected EventTypes $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and band
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();

        // Create calendars
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

        // Create event type (event_types are global, not band-specific)
        $this->eventType = EventTypes::factory()->create();

        $this->actingAs($this->user);
    }

    public function test_updating_event_updates_not_creates_calendar_event()
    {
        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        // Create a booking with an event
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status' => 'confirmed',
            'date' => now()->addDays(10),
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
        ]);

        // Create a GoogleEvents record (simulating previous creation)
        $storedGoogleEventId = 'existing-google-event-id-123';
        GoogleEvents::create([
            'google_eventable_id' => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id' => $this->eventCalendar->id,
            'google_event_id' => $storedGoogleEventId
        ]);

        // Mock the Google Calendar API response
        $mockGoogleEvent = new GoogleEvent();
        $mockGoogleEvent->setId($storedGoogleEventId);

        // Expect updateEvent to be called (not insertEvent)
        // Note: May be called multiple times if observers/jobs trigger it
        $mockService->shouldReceive('updateEvent')
            ->atLeast()->once()
            ->with($this->eventCalendar->calendar_id, $storedGoogleEventId, Mockery::any())
            ->andReturn($mockGoogleEvent);

        $mockService->shouldNotReceive('insertEvent');

        // Update the event - this will trigger the sync
        $event->title = 'Updated Event Title';

        // Directly test the writeToGoogleCalendar method
        $result = $event->writeToGoogleCalendar($this->eventCalendar);

        $this->assertNotFalse($result);
        $this->assertEquals($storedGoogleEventId, $result->getId());

        // Verify only one GoogleEvents record exists
        $this->assertCount(1, GoogleEvents::where('google_eventable_id', $event->id)
            ->where('google_eventable_type', Events::class)
            ->where('band_calendar_id', $this->eventCalendar->id)
            ->get());
    }

    public function test_event_edit_with_existing_calendar_entry()
    {
        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        // Create event
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
        ]);

        $storedGoogleEventId = 'existing-event-id-456';
        GoogleEvents::create([
            'google_eventable_id' => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id' => $this->eventCalendar->id,
            'google_event_id' => $storedGoogleEventId
        ]);

        $mockGoogleEvent = new GoogleEvent();
        $mockGoogleEvent->setId($storedGoogleEventId);

        // Expect update to be called EXACTLY 3 times (for 3 edits), never insert
        $mockService->shouldReceive('updateEvent')
            ->times(3)
            ->andReturn($mockGoogleEvent);

        $mockService->shouldNotReceive('insertEvent');

        // Edit event 3 times
        for ($i = 1; $i <= 3; $i++) {
            $event->title = "Edit #{$i}";
            $event->writeToGoogleCalendar($this->eventCalendar);
        }

        // Verify still only one GoogleEvents record
        $this->assertCount(1, GoogleEvents::where('google_eventable_id', $event->id)
            ->where('band_calendar_id', $this->eventCalendar->id)
            ->get());
    }

    public function test_event_edit_creates_entry_if_none_exists()
    {
        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        // Create event WITHOUT GoogleEvents record
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
        ]);

        $newGoogleEventId = 'new-event-id-789';
        $mockGoogleEvent = new GoogleEvent();
        $mockGoogleEvent->setId($newGoogleEventId);

        // Expect insertEvent to be called ONCE
        $mockService->shouldReceive('insertEvent')
            ->once()
            ->with($this->eventCalendar->calendar_id, Mockery::any())
            ->andReturn($mockGoogleEvent);

        // Write to calendar
        $result = $event->writeToGoogleCalendar($this->eventCalendar);

        $this->assertEquals($newGoogleEventId, $result->getId());
    }

    public function test_defensive_check_prevents_duplicate_on_calendar_type_mismatch()
    {
        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        // Create booking with event
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
        ]);

        // Create GoogleEvents record for the EVENT calendar
        $storedGoogleEventId = 'event-calendar-id-999';
        GoogleEvents::create([
            'google_eventable_id' => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id' => $this->eventCalendar->id, // Event calendar
            'google_event_id' => $storedGoogleEventId
        ]);

        $mockGoogleEvent = new GoogleEvent();
        $mockGoogleEvent->setId($storedGoogleEventId);

        // When we call writeToGoogleCalendar with the SAME calendar
        // it should UPDATE, not INSERT
        $mockService->shouldReceive('updateEvent')
            ->once()
            ->with($this->eventCalendar->calendar_id, $storedGoogleEventId, Mockery::any())
            ->andReturn($mockGoogleEvent);

        $mockService->shouldNotReceive('insertEvent');

        // Call with event calendar - should update existing event
        $result = $event->writeToGoogleCalendar($this->eventCalendar);

        $this->assertEquals($storedGoogleEventId, $result->getId());

        // Verify still only one GoogleEvents record for event calendar
        $this->assertCount(1, GoogleEvents::where('google_eventable_id', $event->id)
            ->where('band_calendar_id', $this->eventCalendar->id)
            ->get());
    }

    public function test_creates_separate_events_for_different_calendars()
    {
        
        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        // Create event
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $booking->date,
            'additional_data' => (object)['public' => true]
        ]);

        $eventCalendarGoogleId = 'event-cal-id-111';
        $publicCalendarGoogleId = 'public-cal-id-222';

        $mockEventGoogleEvent = new GoogleEvent();
        $mockEventGoogleEvent->setId($eventCalendarGoogleId);

        $mockPublicGoogleEvent = new GoogleEvent();
        $mockPublicGoogleEvent->setId($publicCalendarGoogleId);

        // Should insert once for each calendar
        $mockService->shouldReceive('insertEvent')
            ->once()
            ->with($this->eventCalendar->calendar_id, Mockery::any())
            ->andReturn($mockEventGoogleEvent);

        $mockService->shouldReceive('insertEvent')
            ->once()
            ->with($this->publicCalendar->calendar_id, Mockery::any())
            ->andReturn($mockPublicGoogleEvent);

        // Write to both calendars
        $event->writeToGoogleCalendar($this->eventCalendar);
        $event->storeGoogleEventId($this->eventCalendar, $eventCalendarGoogleId);

        $event->writeToGoogleCalendar($this->publicCalendar);
        $event->storeGoogleEventId($this->publicCalendar, $publicCalendarGoogleId);

        // Verify we have TWO GoogleEvents records (one per calendar)
        $this->assertCount(2, GoogleEvents::where('google_eventable_id', $event->id)
            ->where('google_eventable_type', Events::class)
            ->get());

        // Verify each calendar has its own record
        $eventCalendarRecord = GoogleEvents::where('google_eventable_id', $event->id)
            ->where('band_calendar_id', $this->eventCalendar->id)
            ->first();
        $this->assertEquals($eventCalendarGoogleId, $eventCalendarRecord->google_event_id);

        $publicCalendarRecord = GoogleEvents::where('google_eventable_id', $event->id)
            ->where('band_calendar_id', $this->publicCalendar->id)
            ->first();
        $this->assertEquals($publicCalendarGoogleId, $publicCalendarRecord->google_event_id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
