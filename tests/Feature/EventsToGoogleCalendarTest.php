<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Events;
use App\Models\GoogleEvents;
use App\Models\BandCalendars;
use App\Services\GoogleCalendarService;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Foundation\Testing\WithFaker;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EventsToGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;
    protected $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Events::factory()->create();
    }
    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }
    
    /**
     * A basic feature test example.
     */
    public function test_returns_null_for_missing_google_calendar_data(): void
    {
        $this->assertNull($this->event->getGoogleCalendar());
    }

    public function test_returns_null_for_missing_google_event(): void
    {
        $this->assertNull($this->event->getGoogleEvent());
    }

    public function test_returns_google_event(): void
    {
        BandCalendars::factory()->create([
            'band_id' => $this->event->eventable->band->id,
            'type' => 'event'
        ]);
        $googleEvent = GoogleEvents::create([
            'google_event_id' => \Str::uuid(),
            'google_eventable_id' => $this->event->id,
            'google_eventable_type' => get_class($this->event),
            'band_calendar_id' => $this->event->eventable->band->eventCalendar->id
        ]);
        $retrievedEvent = $this->event->getGoogleEvent($this->event->eventable->band->eventCalendar);
        $this->assertNotNull($retrievedEvent);
        $this->assertEquals($googleEvent->id, $retrievedEvent->id);
    }

    public function test_returns_google_calendar(): void
    {
        $bandCalendar = BandCalendars::factory()->create([
            'band_id' => $this->event->eventable->band->id,
            'type' => 'event'
        ]);
        $this->assertInstanceOf(BandCalendars::class, $this->event->getGoogleCalendar());
    }

    public function test_returns_google_calendar_summary(): void
    {
        $this->event->title = 'Test Event';
        $this->assertStringContainsString('Test Event', $this->event->getGoogleCalendarSummary());
    }

    public function test_returns_google_calendar_description(): void
    {
        $this->event->notes = 'Test Description';
        $this->assertEquals('Test Description', $this->event->getGoogleCalendarDescription());
    }

    public function test_returns_google_calendar_start_time(): void
    {
        $this->event->start_time = now();
        $this->assertInstanceOf(EventDateTime::class, $this->event->getGoogleCalendarStartTime());
    }

    public function test_returns_google_calendar_end_time(): void
    {
        $this->event->end_time = now()->addHour();
        $this->assertInstanceOf(EventDateTime::class, $this->event->getGoogleCalendarEndTime());
    }

    public function test_does_not_write_to_google_when_calendar_is_missing(): void
    {
        $this->assertFalse($this->event->writeToGoogleCalendar());
    }

    public function test_writes_to_google_calendar(): void
    {
        BandCalendars::factory()->create([
            'band_id' => $this->event->eventable->band->id,
            'type' => 'event'
        ]);

        $mockService = $this->mock(GoogleCalendarService::class);
        $mockService->shouldReceive('insertEvent')
        ->once()
        ->andReturn(new GoogleEvent());

        $this->assertInstanceOf(GoogleEvent::class, $this->event->writeToGoogleCalendar($this->event->eventable->band->eventCalendar));
    }

    public function test_writes_to_public_google_calendar(): void
    {
        BandCalendars::factory()->create([
            'band_id' => $this->event->eventable->band->id,
            'type' => 'public'
        ]);

        $mockService = $this->mock(GoogleCalendarService::class);
        $mockService->shouldReceive('insertEvent')
        ->once()
        ->andReturn(new GoogleEvent());

        $this->assertInstanceOf(GoogleEvent::class, $this->event->writeToGoogleCalendar($this->event->eventable->band->publicCalendar));
    }

}
