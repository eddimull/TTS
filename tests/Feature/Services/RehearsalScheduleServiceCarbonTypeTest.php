<?php

namespace Tests\Feature\Services;

use App\Models\Bands;
use App\Models\RehearsalSchedule;
use App\Services\RehearsalScheduleService;
use Carbon\Carbon as BaseCarbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for TTS-BAND-16B.
 *
 * EventDataService (and any other caller importing Carbon\Carbon) feeds base
 * Carbon instances through UserEventsService::getEvents() into
 * generateUpcomingRehearsals(). Illuminate\Support\Carbon is a SUBCLASS of
 * Carbon\Carbon, so a parameter typed with the Illuminate flavor rejects the
 * base flavor with a TypeError. The service must accept any CarbonInterface.
 */
class RehearsalScheduleServiceCarbonTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_base_carbon_instances(): void
    {
        $band = Bands::factory()->create();

        RehearsalSchedule::factory()->create([
            'band_id'     => $band->id,
            'frequency'   => 'weekly',
            'day_of_week' => 'monday',
            'active'      => true,
        ]);

        $dates = (new RehearsalScheduleService())
            ->generateUpcomingRehearsals(
                [$band->id],
                BaseCarbon::create(2027, 1, 1, 0, 0, 0),
                BaseCarbon::create(2027, 2, 1, 0, 0, 0),
            )
            ->pluck('date')
            ->map(fn ($d) => BaseCarbon::parse($d)->toDateString())
            ->all();

        $this->assertContains('2027-01-04', $dates);
    }
}
