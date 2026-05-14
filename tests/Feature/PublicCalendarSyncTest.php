<?php

namespace Tests\Feature;

use App\Jobs\ProcessEventCreated;
use App\Jobs\ProcessEventUpdated;
use App\Models\BandCalendars;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventTypes;
use App\Models\Events;
use App\Models\GoogleEvents;
use App\Services\GoogleCalendarService;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PublicCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Bands $band;
    protected BandCalendars $eventCalendar;
    protected BandCalendars $publicCalendar;
    protected EventTypes $eventType;

    /**
     * Tracks calls into the mocked GoogleCalendarService, keyed by method:
     * ['insertEvent' => [[$calId, GoogleEvent], ...], 'updateEvent' => [...], 'deleteEvent' => [...]]
     *
     * @var array<string,array<int,array<int,mixed>>>
     */
    protected array $calendarCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();

        $this->eventCalendar = BandCalendars::factory()->create([
            'band_id'     => $this->band->id,
            'type'        => 'event',
            'calendar_id' => 'event-cal',
        ]);

        $this->publicCalendar = BandCalendars::factory()->create([
            'band_id'     => $this->band->id,
            'type'        => 'public',
            'calendar_id' => 'public-cal',
        ]);

        $this->eventType = EventTypes::factory()->create();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    private function mockCalendarService(): void
    {
        $this->calendarCalls = ['insertEvent' => [], 'updateEvent' => [], 'deleteEvent' => []];

        $mockService = Mockery::mock(GoogleCalendarService::class);

        $mockService->shouldReceive('insertEvent')
            ->andReturnUsing(function (string $calendarId, GoogleEvent $event) {
                $this->calendarCalls['insertEvent'][] = [$calendarId, $event];
                $result = new GoogleEvent();
                $result->setId($calendarId . '-inserted-' . count($this->calendarCalls['insertEvent']));
                return $result;
            });

        $mockService->shouldReceive('updateEvent')
            ->andReturnUsing(function (string $calendarId, string $googleEventId, GoogleEvent $event) {
                $this->calendarCalls['updateEvent'][] = [$calendarId, $googleEventId, $event];
                $result = new GoogleEvent();
                $result->setId($googleEventId);
                return $result;
            });

        $mockService->shouldReceive('deleteEvent')
            ->andReturnUsing(function (string $calendarId, string $googleEventId) {
                $this->calendarCalls['deleteEvent'][] = [$calendarId, $googleEventId];
                return true;
            });

        $this->app->instance(GoogleCalendarService::class, $mockService);
    }

    private function callsForCalendar(string $method, string $calendarId): array
    {
        return array_values(array_filter(
            $this->calendarCalls[$method] ?? [],
            fn(array $call) => $call[0] === $calendarId,
        ));
    }

    private function makeEventForBooking(Bookings $booking, bool $public): Events
    {
        return Events::factory()->create([
            'eventable_id'    => $booking->id,
            'eventable_type'  => Bookings::class,
            'event_type_id'   => $this->eventType->id,
            'date'            => now()->addDays(10)->format('Y-m-d'),
            'additional_data' => ['public' => $public],
        ]);
    }

    public function test_pending_public_event_does_not_sync_to_public_calendar(): void
    {
        $this->mockCalendarService();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status'  => 'pending',
        ]);
        $event = $this->makeEventForBooking($booking, public: true);

        (new ProcessEventCreated($event))->handle();

        $this->assertCount(1, $this->callsForCalendar('insertEvent', $this->eventCalendar->calendar_id));
        $this->assertCount(0, $this->callsForCalendar('insertEvent', $this->publicCalendar->calendar_id));
        $this->assertDatabaseMissing('google_events', [
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id'      => $this->publicCalendar->id,
        ]);
    }

    public function test_confirmed_public_event_syncs_to_public_calendar(): void
    {
        $this->mockCalendarService();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status'  => 'confirmed',
        ]);
        $event = $this->makeEventForBooking($booking, public: true);

        (new ProcessEventCreated($event))->handle();

        $this->assertCount(1, $this->callsForCalendar('insertEvent', $this->publicCalendar->calendar_id));
        $this->assertDatabaseHas('google_events', [
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id'      => $this->publicCalendar->id,
        ]);
    }

    public function test_downgrade_from_confirmed_deletes_existing_public_entry(): void
    {
        $this->mockCalendarService();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status'  => 'pending',
        ]);
        $event = $this->makeEventForBooking($booking, public: true);

        GoogleEvents::create([
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id'      => $this->publicCalendar->id,
            'google_event_id'       => 'stale-public-id',
        ]);

        (new ProcessEventUpdated($event, $event->getOriginal()))->handle();

        $deletes = $this->callsForCalendar('deleteEvent', $this->publicCalendar->calendar_id);
        $this->assertCount(1, $deletes);
        $this->assertSame('stale-public-id', $deletes[0][1]);
        $this->assertDatabaseMissing('google_events', [
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id'      => $this->publicCalendar->id,
        ]);
    }

    public function test_clearing_public_flag_deletes_existing_public_entry(): void
    {
        $this->mockCalendarService();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status'  => 'confirmed',
        ]);
        $event = $this->makeEventForBooking($booking, public: false);

        GoogleEvents::create([
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id'      => $this->publicCalendar->id,
            'google_event_id'       => 'stale-public-id-2',
        ]);

        (new ProcessEventUpdated($event, $event->getOriginal()))->handle();

        $deletes = $this->callsForCalendar('deleteEvent', $this->publicCalendar->calendar_id);
        $this->assertCount(1, $deletes);
        $this->assertSame('stale-public-id-2', $deletes[0][1]);
        $this->assertDatabaseMissing('google_events', [
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
            'band_calendar_id'      => $this->publicCalendar->id,
        ]);
    }

    public function test_public_calendar_event_summary_omits_status_suffix(): void
    {
        $this->mockCalendarService();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status'  => 'confirmed',
        ]);
        $event = Events::factory()->create([
            'eventable_id'    => $booking->id,
            'eventable_type'  => Bookings::class,
            'event_type_id'   => $this->eventType->id,
            'date'            => now()->addDays(10)->format('Y-m-d'),
            'additional_data' => ['public' => true],
            'title'           => 'My Gig',
        ]);

        (new ProcessEventCreated($event))->handle();

        $eventCalCall = $this->callsForCalendar('insertEvent', $this->eventCalendar->calendar_id);
        $publicCalCall = $this->callsForCalendar('insertEvent', $this->publicCalendar->calendar_id);

        $this->assertCount(1, $eventCalCall);
        $this->assertCount(1, $publicCalCall);

        $this->assertSame('My Gig (Confirmed)', $eventCalCall[0][1]->getSummary());
        $this->assertSame('My Gig', $publicCalCall[0][1]->getSummary());
    }
}
