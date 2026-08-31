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

    public function test_unparseable_and_unanchored_entries_are_left_untouched()
    {
        $event = $this->makeEvent([
            ['title' => 'Load In',   'time' => '2026-10-10 16:00'],
            ['title' => 'Breakdown', 'time' => 'TBD'],
            ['title' => 'Doors',     'time' => '19:00'],
            ['title' => 'Rain Date', 'time' => '2026-11-01 20:00'],
        ]);

        $event->update(['date' => '2026-10-20']);

        $this->assertSame([
            'Load In'   => '2026-10-20 16:00',
            'Breakdown' => 'TBD',
            'Doors'     => '19:00',
            'Rain Date' => '2026-11-01 20:00',
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

    public function test_update_without_date_change_leaves_timeline_alone()
    {
        $event = $this->makeEvent([
            ['title' => 'Load In', 'time' => '2026-10-10 16:00'],
        ]);

        $event->update(['title' => 'Renamed']);

        $this->assertSame(['Load In' => '2026-10-10 16:00'], $this->times($event));
    }
}
