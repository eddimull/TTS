<?php

namespace Tests\Unit\Services\Mobile;

use App\Models\RehearsalSchedule;
use App\Services\Mobile\RecurrenceLabelService;
use PHPUnit\Framework\TestCase;

class RecurrenceLabelServiceTest extends TestCase
{
    private RecurrenceLabelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecurrenceLabelService();
    }

    /** Build an unsaved model — no DB needed for a pure formatter. */
    private function schedule(array $attrs): RehearsalSchedule
    {
        return new RehearsalSchedule($attrs);
    }

    public function test_daily(): void
    {
        $this->assertSame('Every day at 7:00 PM', $this->service->format(
            $this->schedule(['frequency' => 'daily', 'default_time' => '19:00:00'])));
    }

    public function test_weekday(): void
    {
        $this->assertSame('Weekdays at 6:30 PM', $this->service->format(
            $this->schedule(['frequency' => 'weekday', 'default_time' => '18:30:00'])));
    }

    public function test_weekly_single_day_from_selected_days(): void
    {
        $this->assertSame('Every Tuesday at 7:00 PM', $this->service->format(
            $this->schedule([
                'frequency' => 'weekly',
                'selected_days' => ['tuesday'],
                'default_time' => '19:00:00',
            ])));
    }

    public function test_weekly_multiple_days(): void
    {
        $this->assertSame('Every Tuesday & Thursday at 7:00 PM', $this->service->format(
            $this->schedule([
                'frequency' => 'weekly',
                'selected_days' => ['tuesday', 'thursday'],
                'default_time' => '19:00:00',
            ])));
    }

    public function test_weekly_legacy_day_of_week_fallback(): void
    {
        $this->assertSame('Every Wednesday', $this->service->format(
            $this->schedule([
                'frequency' => 'weekly',
                'day_of_week' => 'wednesday',
                'default_time' => null,
            ])));
    }

    public function test_weekly_with_no_days_falls_back_to_generic(): void
    {
        $this->assertSame('Weekly', $this->service->format(
            $this->schedule(['frequency' => 'weekly'])));
    }

    public function test_monthly_day_of_month(): void
    {
        $this->assertSame('Monthly on the 15th at 7:00 PM', $this->service->format(
            $this->schedule([
                'frequency' => 'monthly',
                'monthly_pattern' => 'day_of_month',
                'day_of_month' => 15,
                'default_time' => '19:00:00',
            ])));
    }

    public function test_monthly_pattern_weekday(): void
    {
        $this->assertSame('First Monday of each month at 7:00 PM', $this->service->format(
            $this->schedule([
                'frequency' => 'monthly',
                'monthly_pattern' => 'first',
                'monthly_weekday' => 'monday',
                'default_time' => '19:00:00',
            ])));
    }

    public function test_monthly_without_pattern_is_generic(): void
    {
        $this->assertSame('Monthly', $this->service->format(
            $this->schedule(['frequency' => 'monthly'])));
    }

    public function test_custom(): void
    {
        $this->assertSame('Custom schedule', $this->service->format(
            $this->schedule(['frequency' => 'custom', 'default_time' => '19:00:00'])));
    }

    public function test_ordinals(): void
    {
        foreach ([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 11 => '11th', 21 => '21st', 22 => '22nd', 23 => '23rd'] as $day => $expected) {
            $this->assertStringContainsString("Monthly on the {$expected}", $this->service->format(
                $this->schedule([
                    'frequency' => 'monthly',
                    'monthly_pattern' => 'day_of_month',
                    'day_of_month' => $day,
                ])));
        }
    }
}
