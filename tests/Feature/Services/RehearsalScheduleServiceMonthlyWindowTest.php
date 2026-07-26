<?php

namespace Tests\Feature\Services;

use App\Models\Bands;
use App\Models\RehearsalSchedule;
use App\Services\RehearsalScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pins the [start, end) window contract for the monthly generator.
 *
 * The daily, weekday and weekly generators all loop on `lt($endDate)`, making
 * their windows end-exclusive. Monthly must match, so callers can reason about
 * one contract. Callers that want an inclusive `until` date pass endOfDay()
 * (see RehearsalsController::schedules).
 */
class RehearsalScheduleServiceMonthlyWindowTest extends TestCase
{
    use RefreshDatabase;

    private function monthlySchedule(int $dayOfMonth): RehearsalSchedule
    {
        $band = Bands::factory()->create();

        return RehearsalSchedule::factory()->create([
            'band_id'         => $band->id,
            'frequency'       => 'monthly',
            'monthly_pattern' => 'day_of_month',
            'day_of_month'    => $dayOfMonth,
            'day_of_week'     => null,
            'active'          => true,
        ]);
    }

    /** @return array<int, string> generated occurrence dates */
    private function generate(RehearsalSchedule $schedule, Carbon $start, Carbon $end): array
    {
        return (new RehearsalScheduleService())
            ->generateUpcomingRehearsals([$schedule->band_id], $start, $end)
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : Carbon::parse($d)->toDateString())
            ->values()
            ->all();
    }

    public function test_monthly_occurrence_exactly_on_end_date_is_excluded(): void
    {
        $schedule = $this->monthlySchedule(15);

        $start = Carbon::create(2027, 1, 1, 0, 0, 0);
        // End lands exactly on an occurrence at 00:00 — must be excluded.
        $end = Carbon::create(2027, 3, 15, 0, 0, 0);

        $dates = $this->generate($schedule, $start, $end);

        $this->assertContains('2027-01-15', $dates);
        $this->assertContains('2027-02-15', $dates);
        $this->assertNotContains('2027-03-15', $dates,
            'monthly must be end-exclusive [start, end), matching daily/weekly/weekday');
    }

    public function test_monthly_occurrence_the_day_before_end_date_is_included(): void
    {
        $schedule = $this->monthlySchedule(15);

        $start = Carbon::create(2027, 1, 1, 0, 0, 0);
        $end = Carbon::create(2027, 3, 16, 0, 0, 0);

        $dates = $this->generate($schedule, $start, $end);

        $this->assertContains('2027-03-15', $dates,
            'an occurrence strictly before the end bound must be included');
    }

    public function test_end_of_day_end_bound_keeps_the_occurrence_on_that_date(): void
    {
        // This is how RehearsalsController::schedules calls the service, so an
        // inclusive `until` date still yields that date's occurrence.
        $schedule = $this->monthlySchedule(15);

        $start = Carbon::create(2027, 1, 1, 0, 0, 0);
        $end = Carbon::create(2027, 3, 15, 0, 0, 0)->endOfDay();

        $dates = $this->generate($schedule, $start, $end);

        $this->assertContains('2027-03-15', $dates,
            'endOfDay() upstream must preserve the inclusive-until behavior');
    }
}
