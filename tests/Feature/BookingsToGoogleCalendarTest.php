<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bookings;
use Mockery\MockInterface;
use Illuminate\Support\Str;
use App\Models\GoogleEvents as LocalGoogleEvents;
use App\Models\BandCalendars;
use Google\Service\Calendar\Calendar;
use App\Services\GoogleCalendarService;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Foundation\Testing\WithFaker;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingsToGoogleCalendarTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    protected $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->booking = Bookings::factory()->create();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    public function test_returns_null_for_missing_google_calendar_data(): void
    {
        $this->assertNull($this->booking->getGoogleCalendar());
    }

    public function test_returns_google_calendar(): void
    {
        $bandCalendar = BandCalendars::factory()->create([
            'band_id' => $this->booking->band->id,
            'type' => 'booking'
        ]);
        $this->assertInstanceOf(BandCalendars::class, $this->booking->getGoogleCalendar());
    }

    public function test_returns_google_event_as_null_when_no_event_exists(): void
    {
        $this->assertNull($this->booking->getGoogleEvent());
    }

    public function test_returns_google_event_when_event_exists(): void
    {
        BandCalendars::factory()->create([
            'band_id' => $this->booking->band->id,
            'type' => 'booking'
        ]);

        $googleEvent = LocalGoogleEvents::create([
            'google_event_id' => \Str::uuid(),
            'google_eventable_id' => $this->booking->id,
            'google_eventable_type' => get_class($this->booking),
            'band_calendar_id' => $this->booking->band->bookingCalendar->id
        ]);


        $retrievedEvent = $this->booking->getGoogleEvent();
        $this->assertNotNull($retrievedEvent);
        $this->assertEquals($googleEvent->id, $retrievedEvent->id);
    }

    public function test_returns_google_calendar_summary(): void
    {   
        $detailsThatShouldBeThere = [$this->booking->name, $this->booking->status];
        foreach ($detailsThatShouldBeThere as $detail) {
            $this->assertStringContainsString(Str::lower($detail), Str::lower($this->booking->getGoogleCalendarSummary()));
        }
    }

    public function test_returns_google_calendar_description(): void
    {
        $detailsThatShouldBeThere = [$this->booking->status, $this->booking->venue_name, $this->booking->venue_address];
        foreach ($detailsThatShouldBeThere as $detail) {
            $this->assertStringContainsString(Str::lower($detail), Str::lower($this->booking->getGoogleCalendarDescription()));
        }
    }

    public function test_returns_google_calendar_start_time(): void
    {
        $startTime = $this->booking->getGoogleCalendarStartTime();
        $this->assertEquals($startTime->dateTime, $this->booking->startDateTime);
        $this->assertInstanceOf(EventDateTime::class, $startTime);
    }

    public function test_returns_google_calendar_end_time(): void
    {
        $endTime = $this->booking->getGoogleCalendarEndTime();
        $this->assertEquals($endTime->dateTime, $this->booking->endDateTime);
        $this->assertInstanceOf(EventDateTime::class, $endTime);
    }

    public function test_does_not_write_to_google_when_calendar_is_missing(): void
    {
        $this->assertFalse($this->booking->writeToGoogleCalendar());
    }

    public function test_writes_to_google_calendar(): void
    {
        BandCalendars::factory()->create([
            'band_id' => $this->booking->band->id,
            'type' => 'booking'
        ]);
        $mockService = $this->mock(GoogleCalendarService::class);
        $mockService->shouldReceive('insertEvent')
        ->once()
        ->andReturn(new GoogleEvent());
        $this->assertInstanceOf(GoogleEvent::class, $this->booking->writeToGoogleCalendar($this->booking->band->bookingCalendar));
    }
}
