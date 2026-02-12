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

/**
 * Test for debouncing the queue to update the google calendar
 */
class RapidEventUpdatesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Bands $band;
    protected BandCalendars $eventCalendar;
    protected EventTypes $eventType;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_rapid_succession_updates_sync_final_version()
    {
        $syncedTitles = [];

        $mockService = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockService);

        // Track all synced titles in order
        $mockService->shouldReceive('insertEvent')
            ->andReturnUsing(function ($calendarId, $eventData) use (&$syncedTitles) {
                $title = $eventData->getSummary();
                $syncedTitles[] = $title;
                \Log::info("MOCK: Inserted event with title: {$title}");

                $event = new GoogleEvent();
                $event->setId('event-' . uniqid());
                return $event;
            });

        $mockService->shouldReceive('updateEvent')
            ->andReturnUsing(function ($calendarId, $eventId, $eventData) use (&$syncedTitles) {
                $title = $eventData->getSummary();
                $syncedTitles[] = $title;
                \Log::info("MOCK: Updated event with title: {$title}");

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
            'title' => 'Initial',
        ]);

        // Simulate rapid successive saves (YOUR EXACT SCENARIO)
        $event->title = 'test 1';
        $event->save();

        $event->title = 'test 12';
        $event->save();

        $event->title = 'test 123';
        $event->save();

        $event->title = 'test 1234';
        $event->save();

        $lastSyncedTitle = end($syncedTitles);

        $this->assertEquals(
            'test 1234',
            $lastSyncedTitle,
            'Google Calendar should have "test 1234" (the final version), not an intermediate version. ' .
            'All synced titles: ' . implode(' → ', $syncedTitles)
        );


        $event->refresh();
        $this->assertEquals('test 1234', $event->title);

        // Debug output
        fwrite(STDERR, 'Synced titles in order: ' . implode(' → ', $syncedTitles) . PHP_EOL);
    }

    /**
     * Test that the final state is synced even with very rapid updates
     */
    public function test_very_rapid_updates_10_edits_sync_final_state()
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
            'title' => 'Initial',
        ]);

        // Make 10 rapid updates
        for ($i = 1; $i <= 10; $i++) {
            $event->title = "Version {$i}";
            $event->save();
        }

        // The last synced title should be "Version 10"
        $lastSyncedTitle = end($syncedTitles);

        $this->assertEquals(
            'Version 10',
            $lastSyncedTitle,
            'Google Calendar should have the final version (Version 10), not an intermediate one. ' .
            'Synced: ' . implode(', ', array_slice($syncedTitles, -5)) // Show last 5
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
