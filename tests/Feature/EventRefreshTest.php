<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\EventTypes;
use App\Models\BandCalendars;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Google\Service\Calendar\Event as GoogleEvent;
use Mockery;


class EventRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Bands $band;
    protected BandCalendars $eventCalendar;
    protected EventTypes $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        // Force synchronous queue for testing
        config(['queue.default' => 'sync']);

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();

        $this->eventCalendar = BandCalendars::factory()->create([
            'band_id' => $this->band->id,
            'type' => 'event',
            'calendar_id' => 'test-event-calendar-id'
        ]);

        $this->eventType = EventTypes::factory()->create();
        $this->actingAs($this->user);
    }


    public function test_rapid_title_updates_sync_latest_version_to_google_calendar()
    {
        $syncedTitles = [];

        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        // Track what titles get synced to Google Calendar
        $mockService->shouldReceive('insertEvent')
            ->andReturnUsing(function ($calendarId, $eventData) use (&$syncedTitles) {
                $title = $eventData->getSummary();
                $syncedTitles[] = $title;

                $event = new GoogleEvent();
                $event->setId('event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('updateEvent')
            ->andReturnUsing(function ($calendarId, $eventId, $eventData) use (&$syncedTitles) {
                $title = $eventData->getSummary();
                $syncedTitles[] = $title;

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


        $event->title = 'test 1';
        $event->save();

        $event->title = 'test 2';
        $event->save();


        $this->assertContains(
            'test 2',
            $syncedTitles,
            'Google Calendar should receive "test 2" (the final version), not "test 1" (intermediate version). ' .
            'If this fails, the job is not refreshing from the database. ' .
            'Synced titles: ' . implode(', ', $syncedTitles)
        );

        // Verify the database has the final version
        $event->refresh();
        $this->assertEquals('test 2', $event->title);
    }

    public function test_multiple_field_updates_sync_latest_state()
    {
        $syncedEvents = [];

        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        $mockService->shouldReceive('insertEvent')
            ->andReturnUsing(function ($calendarId, $eventData) use (&$syncedEvents) {
                $syncedEvents[] = [
                    'title' => $eventData->getSummary(),
                    'description' => $eventData->getDescription(),
                ];

                $event = new GoogleEvent();
                $event->setId('event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('updateEvent')
            ->andReturnUsing(function ($calendarId, $eventId, $eventData) use (&$syncedEvents) {
                $syncedEvents[] = [
                    'title' => $eventData->getSummary(),
                    'description' => $eventData->getDescription(),
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
            'title' => 'Version 1',
            'notes' => 'Notes 1',
        ]);

        $event->title = 'Version 2';
        $event->save();

        $event->notes = 'Notes 2';
        $event->save();

        $event->title = 'Final Version';
        $event->notes = 'Final Notes';
        $event->save();


        $lastSynced = end($syncedEvents);


        $this->assertEquals('Final Version', $lastSynced['title'], 'Latest title should be synced');
        $this->assertStringContainsString('Final Notes', $lastSynced['description'], 'Latest notes should be included in description');


        $event->refresh();
        $this->assertEquals('Final Version', $event->title);
        $this->assertEquals('Final Notes', $event->notes);
    }


    public function test_delayed_job_syncs_current_state_not_dispatch_state()
    {
        $syncedTitles = [];


        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        $mockService->shouldReceive('insertEvent')
            ->andReturnUsing(function ($calendarId, $eventData) use (&$syncedTitles) {
                $syncedTitles[] = $eventData->getSummary();
                $event = new GoogleEvent();
                $event->setId('event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('updateEvent')
            ->andReturnUsing(function ($calendarId, $eventId, $eventData) use (&$syncedTitles) {
                $syncedTitles[] = $eventData->getSummary();
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
            'title' => 'Dispatch Time Title',
        ]);

        $event->updateQuietly(['title' => 'Current Database Title']);

        $event->title = 'Final Title After Refresh';
        $event->save();

        // The last sync should have the final title
        $this->assertContains('Final Title After Refresh', $syncedTitles);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
