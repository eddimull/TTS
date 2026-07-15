<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RehearsalCalendarRepresentationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{rehearsal: Rehearsal, event: Events} */
    private function makeRehearsalWithEvent(bool $cancelled): array
    {
        $band = Bands::factory()->create();
        $schedule = RehearsalSchedule::factory()->weekly()->create([
            'band_id' => $band->id,
            'name'    => 'Tuesday Practice',
        ]);
        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
            'is_cancelled'          => $cancelled,
        ]);
        $event = Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'title'          => 'Tuesday Practice',
            'date'           => now()->addDays(5)->format('Y-m-d'),
            'start_time'     => '19:00:00',
        ]);

        return compact('rehearsal', 'event');
    }

    public function test_cancelled_rehearsal_event_gets_prefixed_summary_and_red_color(): void
    {
        ['rehearsal' => $rehearsal, 'event' => $event] = $this->makeRehearsalWithEvent(true);

        $this->assertSame('Cancelled: Tuesday Practice', $event->getGoogleCalendarSummary());
        $this->assertSame('11', $event->getGoogleCalendarColor());

        $this->assertSame('Cancelled: Tuesday Practice', $rehearsal->getGoogleCalendarSummary());
        $this->assertSame('11', $rehearsal->getGoogleCalendarColor());
    }

    public function test_active_rehearsal_event_keeps_plain_summary_and_yellow_color(): void
    {
        ['rehearsal' => $rehearsal, 'event' => $event] = $this->makeRehearsalWithEvent(false);

        $this->assertSame('Tuesday Practice', $event->getGoogleCalendarSummary());
        $this->assertSame('5', $event->getGoogleCalendarColor());

        $this->assertSame('Tuesday Practice', $rehearsal->getGoogleCalendarSummary());
        $this->assertSame('5', $rehearsal->getGoogleCalendarColor());
    }
}
