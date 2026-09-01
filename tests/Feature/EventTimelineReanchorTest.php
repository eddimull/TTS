<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\EventTypes;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EventTimelineReanchorTest extends TestCase
{
    use RefreshDatabase;

    protected Bands $band;
    protected Bookings $booking;
    protected EventTypes $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->band = Bands::factory()->create();
        $this->eventType = EventTypes::factory()->create();
        $this->booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'status' => 'confirmed',
        ]);
    }

    private function makeEvent(array $times, string $date = '2026-10-10'): Events
    {
        return Events::factory()->create([
            'eventable_id' => $this->booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id' => $this->eventType->id,
            'date' => $date,
            'start_time' => '20:00',
            'additional_data' => ['times' => $times],
        ]);
    }

    private function times(Events $event): array
    {
        return collect($event->refresh()->additional_data->times)
            ->mapWithKeys(fn ($t) => [$t->title => $t->time])
            ->all();
    }

    public function test_changing_event_date_shifts_timeline_dates_preserving_time_of_day()
    {
        $event = $this->makeEvent([
            ['title' => 'Load In',    'time' => '2026-10-10 16:00'],
            ['title' => 'Soundcheck', 'time' => '2026-10-10 17:00'],
        ]);

        $event->update(['date' => '2026-10-20']);

        $this->assertSame([
            'Load In'    => '2026-10-20 16:00',
            'Soundcheck' => '2026-10-20 17:00',
        ], $this->times($event));
    }

    public function test_next_day_timeline_entries_keep_their_day_offset()
    {
        $event = $this->makeEvent([
            ['title' => 'Load In',  'time' => '2026-10-10 16:00'],
            ['title' => 'End Time', 'time' => '2026-10-11 00:30'],
        ]);

        $event->update(['date' => '2026-10-20']);

        $this->assertSame([
            'Load In'  => '2026-10-20 16:00',
            'End Time' => '2026-10-21 00:30',
        ], $this->times($event));
    }

    public function test_unparseable_entries_are_left_untouched()
    {
        $event = $this->makeEvent([
            ['title' => 'Load In',   'time' => '2026-10-10 16:00'],
            ['title' => 'Breakdown', 'time' => 'TBD'],
            ['title' => 'Doors',     'time' => '19:00'],
        ]);

        $event->update(['date' => '2026-10-20']);

        $this->assertSame([
            'Load In'   => '2026-10-20 16:00',
            'Breakdown' => 'TBD',
            'Doors'     => '19:00',
        ], $this->times($event));
    }

    public function test_already_drifted_entries_snap_to_the_new_date()
    {
        // A timeline that drifted before the re-anchor hook existed is
        // anchored to some long-gone date, far outside ±1 day of the event's
        // current date. Changing the date must heal it — snap every dated
        // entry to the new date, preserving time-of-day. This is the exact
        // "created with the wrong date, timeline stuck there" report.
        $event = $this->makeEvent([
            ['title' => 'Load In',    'time' => '2026-08-31 15:00'],
            ['title' => 'Soundcheck', 'time' => '2026-08-31 16:00'],
            ['title' => 'Quiet',      'time' => '2026-08-31 18:00'],
        ], '2026-09-04');

        $event->update(['date' => '2026-09-11']);

        $this->assertSame([
            'Load In'    => '2026-09-11 15:00',
            'Soundcheck' => '2026-09-11 16:00',
            'Quiet'      => '2026-09-11 18:00',
        ], $this->times($event));
    }

    public function test_stale_timeline_sent_alongside_new_date_is_reanchored()
    {
        // Mirrors the mobile edit screen: one update() carrying the new date
        // AND a timeline still anchored to the old date.
        $event = $this->makeEvent([
            ['title' => 'Load In', 'time' => '2026-10-10 16:00'],
        ]);

        $event->update([
            'date' => '2026-10-20',
            'additional_data' => ['times' => [
                ['title' => 'Load In', 'time' => '2026-10-10 15:00'],
            ]],
        ]);

        $this->assertSame(['Load In' => '2026-10-20 15:00'], $this->times($event));
    }

    public function test_reanchors_cached_additional_data_object_with_array_entries()
    {
        // Mirrors Api/Mobile/EventsController::update exactly: the controller
        // reads $event->additional_data (priming Laravel's attribute object
        // cache), EventDataService replaces ->times with an array of ARRAYS
        // built from the request, and the same (cached) object is passed back
        // through update(). The hook must re-anchor array entries too — on
        // device this path left the timeline on the old date while the date
        // column moved.
        $event = $this->makeEvent([
            ['title' => 'Load In', 'time' => '2026-10-10 16:00'],
        ]);
        $event = Events::find($event->id);

        $ad = $event->additional_data; // prime the accessor / object cache
        $ad->times = [
            ['title' => 'Load In', 'time' => '2026-10-10 15:00'],
            ['title' => 'Soundcheck', 'time' => '2026-10-10 16:00'],
        ];

        $event->update([
            'date' => '2026-10-20',
            'additional_data' => $ad,
        ]);

        $this->assertSame([
            'Load In'    => '2026-10-20 15:00',
            'Soundcheck' => '2026-10-20 16:00',
        ], $this->times($event));
    }

    public function test_update_without_date_change_leaves_timeline_alone()
    {
        $event = $this->makeEvent([
            ['title' => 'Load In', 'time' => '2026-10-10 16:00'],
        ]);

        $event->update(['title' => 'Renamed']);

        $this->assertSame(['Load In' => '2026-10-10 16:00'], $this->times($event));
    }
}
