<?php

namespace Tests\Unit\Services;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Services\PriceEstimatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PriceEstimatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private PriceEstimatorService $service;
    private Bands $band;
    private EventTypes $wedding;
    private EventTypes $barGig;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00'));

        $this->service = app(PriceEstimatorService::class);
        $this->band = Bands::factory()->create([
            'address' => '100 Main St',
            'city' => 'Lafayette',
            'state' => 'LA',
            'zip' => '70506',
        ]);
        $this->wedding = EventTypes::factory()->create(['name' => 'Wedding']);
        $this->barGig = EventTypes::factory()->create(['name' => 'Bar Gig']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeBooking(
        EventTypes $type,
        float $price,
        string $date,
        ?string $start = '19:00',
        ?string $end = '23:00',
        string $status = 'confirmed',
        ?Bands $band = null,
    ): Bookings {
        $booking = Bookings::factory()->create([
            'band_id' => ($band ?? $this->band)->id,
            'event_type_id' => $type->id,
            'price' => $price,
            'status' => $status,
            'name' => "Booking {$date}",
        ]);

        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'event_type_id' => $type->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'venue_name' => "Venue {$date}",
        ]);

        return $booking;
    }

    /**
     * Three May-2026 Saturday weddings, 4h each: season 1.1 × dow 1.0 ×
     * duration 1.0 normalizes 1100/2200/3300 to 1000/2000/3000.
     */
    private function seedWeddingHistory(): void
    {
        $this->service->saveSettings($this->band, ['annual_increase_pct' => 0]);
        $this->makeBooking($this->wedding, 1100, '2026-05-02');
        $this->makeBooking($this->wedding, 2200, '2026-05-09');
        $this->makeBooking($this->wedding, 3300, '2026-05-16');
    }

    private function line(array $result, string $key): ?array
    {
        return collect($result['breakdown'])->firstWhere('key', $key);
    }

    private function warningCodes(array $result): array
    {
        return array_column($result['warnings'], 'code');
    }

    // ── Pure helpers ─────────────────────────────────────────────────────

    public function test_hours_between_handles_normal_and_midnight_crossing(): void
    {
        $this->assertSame(4.0, PriceEstimatorService::hoursBetween('19:00', '23:00'));
        $this->assertSame(4.0, PriceEstimatorService::hoursBetween('21:00:00', '01:00:00'));
        $this->assertSame(4.5, PriceEstimatorService::hoursBetween('22:30', '03:00'));
        $this->assertNull(PriceEstimatorService::hoursBetween(null, '23:00'));
        $this->assertNull(PriceEstimatorService::hoursBetween('19:00', '19:00'));
        $this->assertNull(PriceEstimatorService::hoursBetween('garbage', '19:00'));
    }

    public function test_weighted_percentile(): void
    {
        $equal = [
            ['value' => 300, 'weight' => 1],
            ['value' => 100, 'weight' => 1],
            ['value' => 200, 'weight' => 1],
        ];
        $this->assertSame(200.0, PriceEstimatorService::weightedPercentile($equal, 0.5));
        $this->assertSame(100.0, PriceEstimatorService::weightedPercentile($equal, 0.25));
        $this->assertSame(300.0, PriceEstimatorService::weightedPercentile($equal, 0.75));

        $skewed = [
            ['value' => 100, 'weight' => 0.25],
            ['value' => 200, 'weight' => 0.25],
            ['value' => 300, 'weight' => 4],
        ];
        $this->assertSame(300.0, PriceEstimatorService::weightedPercentile($skewed, 0.5));
        $this->assertSame(0.0, PriceEstimatorService::weightedPercentile([], 0.5));
    }

    public function test_round_to(): void
    {
        $this->assertSame(11250.0, PriceEstimatorService::roundTo(11237, 50));
        $this->assertSame(11200.0, PriceEstimatorService::roundTo(11224, 50));
        $this->assertSame(11300.0, PriceEstimatorService::roundTo(11250, 100));
        $this->assertSame(123.46, PriceEstimatorService::roundTo(123.456, 0));
    }

    public function test_normalize_sample_removes_season_dow_duration_and_inflates(): void
    {
        $settings = $this->service->settingsFor($this->band);
        $today = Carbon::today();

        // May (1.1), Saturday (1.0), 4h (1.0), dated today-ish → ≈1000
        $recent = $this->service->normalizeSample([
            'price' => 1100.0, 'date' => Carbon::parse('2026-05-16'), 'start_time' => '19:00:00',
            'end_time' => '23:00:00', 'event_type_id' => $this->wedding->id,
        ], array_merge($settings, ['annual_increase_pct' => 0]), $today);
        $this->assertEqualsWithDelta(1000.0, $recent['value'], 0.001);

        // 5h crossing midnight → duration factor 1.15
        $long = $this->service->normalizeSample([
            'price' => 1100.0 * 1.15, 'date' => Carbon::parse('2026-05-16'), 'start_time' => '21:00:00',
            'end_time' => '02:00:00', 'event_type_id' => $this->wedding->id,
        ], array_merge($settings, ['annual_increase_pct' => 0]), $today);
        $this->assertEqualsWithDelta(1000.0, $long['value'], 0.001);

        // Missing times → duration factor 1.0
        $noTimes = $this->service->normalizeSample([
            'price' => 1100.0, 'date' => Carbon::parse('2026-05-16'), 'start_time' => null,
            'end_time' => null, 'event_type_id' => $this->wedding->id,
        ], array_merge($settings, ['annual_increase_pct' => 0]), $today);
        $this->assertEqualsWithDelta(1000.0, $noTimes['value'], 0.001);

        // ~2 years ago: value inflated by 1.03^2 and weight halved (2-yr half-life).
        $twoYearsAgo = Carbon::today()->subDays((int) round(365.25 * 2));
        $old = $this->service->normalizeSample([
            'price' => 1000.0, 'date' => $twoYearsAgo, 'start_time' => null, 'end_time' => null,
            'event_type_id' => $this->wedding->id,
        ], $settings, $today);
        $divisor = $this->service->seasonMultiplier($settings, $twoYearsAgo) * $this->service->dayOfWeekMultiplier($settings, $twoYearsAgo);
        $this->assertEqualsWithDelta(1000.0 / $divisor * 1.03 ** 2, $old['value'], 0.5);
        $this->assertEqualsWithDelta(0.5, $old['weight'], 0.001);
    }

    public function test_settings_merge_defaults_under_stored_values(): void
    {
        $merged = $this->service->saveSettings($this->band, [
            'season_multipliers' => ['5' => 1.3],
            'round_to' => 100,
            'add_ons' => [['key' => 'horns', 'label' => 'Horn section', 'amount' => 900]],
            'unknown_key' => 'ignored',
        ]);

        $this->assertEquals(1.3, $merged['season_multipliers'][5]);
        $this->assertEquals(1.1, $merged['season_multipliers'][3]); // default kept
        $this->assertEquals(100, $merged['round_to']);
        $this->assertCount(1, $merged['add_ons']);
        $this->assertArrayNotHasKey('unknown_key', $merged);

        $presented = json_decode(json_encode($this->service->presentSettings($merged)), true);
        $this->assertSame(['0', '1', '2', '3', '4', '5', '6'], array_map('strval', array_keys($presented['day_of_week_multipliers'])));
        $this->assertStringContainsString('"base_rates":{}', json_encode($this->service->presentSettings($merged)));
        $this->assertStringContainsString('"day_of_week_multipliers":{"0":0.9', json_encode($this->service->presentSettings($merged)));
    }

    // ── Baseline ─────────────────────────────────────────────────────────

    public function test_history_baseline_uses_weighted_median_and_quartiles(): void
    {
        $this->seedWeddingHistory();

        // Saturday May 15 2027, 4h: 2000 × 1.1 season × 1.0 dow
        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'start_time' => '19:00',
            'end_time' => '23:00',
        ]);

        $this->assertSame('history', $result['baseline_source']);
        $this->assertSame('medium', $result['confidence']);
        $this->assertSame(3, $result['sample_size']);
        $this->assertEqualsWithDelta(2000.0, $result['baseline'], 0.01);
        $this->assertSame(2200.0, $result['estimate']);
        $this->assertSame(1100.0, $result['low']);
        $this->assertSame(3300.0, $result['high']);
        $this->assertSame(4.0, $result['duration_hours']);

        $this->assertSame('Wedding baseline (3 past bookings)', $this->line($result, 'baseline')['label']);
        $season = $this->line($result, 'season');
        $this->assertSame('May (peak season)', $season['label']);
        $this->assertEqualsWithDelta(1.1, $season['factor'], 0.0001);
        $this->assertEqualsWithDelta(200.0, $season['amount'], 0.01);
        $dow = $this->line($result, 'day_of_week');
        $this->assertSame('Saturday', $dow['label']);
        $this->assertEqualsWithDelta(0.0, $dow['amount'], 0.01);
        $this->assertNull($this->line($result, 'duration'), 'factor-1.0 duration line is omitted');
    }

    public function test_cancelled_and_zero_price_bookings_are_excluded(): void
    {
        $this->seedWeddingHistory();
        $this->makeBooking($this->wedding, 99000, '2026-05-23', status: 'cancelled');
        $this->makeBooking($this->wedding, 0, '2026-05-30');

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
        ]);

        $this->assertSame(3, $result['sample_size']);
    }

    public function test_other_bands_history_is_not_used(): void
    {
        $other = Bands::factory()->create();
        $this->makeBooking($this->wedding, 5000, '2026-05-02', band: $other);
        $this->makeBooking($this->wedding, 5000, '2026-05-09', band: $other);
        $this->makeBooking($this->wedding, 5000, '2026-05-16', band: $other);

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
        ]);

        $this->assertNull($result['estimate']);
        $this->assertContains('no_baseline', $this->warningCodes($result));
    }

    public function test_falls_back_to_band_average_with_low_confidence(): void
    {
        $this->seedWeddingHistory();

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->barGig->id,
            'date' => '2027-05-15',
        ]);

        $this->assertSame('band_average', $result['baseline_source']);
        $this->assertSame('low', $result['confidence']);
        $this->assertSame(3, $result['sample_size']);
        $this->assertEqualsWithDelta(2000.0, $result['baseline'], 0.01);
        $this->assertContains('low_sample', $this->warningCodes($result));
        $this->assertSame([], $result['comparables']);
    }

    public function test_no_history_returns_null_estimate_with_warning(): void
    {
        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
        ]);

        $this->assertNull($result['estimate']);
        $this->assertNull($result['low']);
        $this->assertNull($result['high']);
        $this->assertSame('none', $result['baseline_source']);
        $this->assertSame([], $result['breakdown']);
        $this->assertContains('no_baseline', $this->warningCodes($result));
    }

    public function test_manual_base_rate_overrides_history(): void
    {
        $this->seedWeddingHistory();
        $this->service->saveSettings($this->band, ['base_rates' => [(string) $this->wedding->id => 5000]]);

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
        ]);

        $this->assertSame('manual', $result['baseline_source']);
        $this->assertSame(5000.0, $result['baseline']);
        $this->assertSame(5500.0, $result['estimate']);
        $this->assertSame('Wedding base rate (manual)', $this->line($result, 'baseline')['label']);
        // Range keeps the history spread (P25/median = 0.5, P75/median = 1.5)
        $this->assertSame(2750.0, $result['low']);
        $this->assertSame(8250.0, $result['high']);
    }

    public function test_confidence_is_high_with_ten_samples(): void
    {
        $this->service->saveSettings($this->band, ['annual_increase_pct' => 0]);
        for ($i = 0; $i < 10; $i++) {
            $this->makeBooking($this->wedding, 1100, Carbon::parse('2026-05-02')->addWeeks($i % 4)->toDateString());
        }

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
        ]);

        $this->assertSame('high', $result['confidence']);
        $this->assertSame(10, $result['sample_size']);
    }

    // ── Multipliers ──────────────────────────────────────────────────────

    public function test_duration_multiplier_handles_midnight_crossing(): void
    {
        $this->seedWeddingHistory();

        // 19:00 → 01:00 = 6h vs standard 4 → 1 + 0.15×2 = 1.3
        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'start_time' => '19:00',
            'end_time' => '01:00',
        ]);

        $this->assertSame(6.0, $result['duration_hours']);
        $duration = $this->line($result, 'duration');
        $this->assertEqualsWithDelta(1.3, $duration['factor'], 0.0001);
        $this->assertEqualsWithDelta(660.0, $duration['amount'], 0.01); // 2200 × 0.3, sequential
        $this->assertSame(2850.0, $result['estimate']); // 2860 → nearest 50
    }

    public function test_duration_factor_is_clamped_and_per_type_standard_hours_apply(): void
    {
        $this->seedWeddingHistory();
        $this->service->saveSettings($this->band, ['standard_hours_by_type' => [(string) $this->wedding->id => 5]]);

        // 1h vs standard 5 → 1 − 0.6 = 0.4, clamped to 0.75
        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'duration_hours' => 1,
        ]);

        $this->assertEqualsWithDelta(0.75, $this->line($result, 'duration')['factor'], 0.0001);
    }

    public function test_default_duration_is_standard_hours(): void
    {
        $this->seedWeddingHistory();

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
        ]);

        $this->assertSame(4.0, $result['duration_hours']);
        $this->assertNull($this->line($result, 'duration'));
    }

    public function test_recurring_special_date_multiplier(): void
    {
        $this->seedWeddingHistory();

        // Dec 31 2027 is a Friday: 2000 × 1.05 × 0.95 × 1.5
        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-12-31',
        ]);

        $special = $this->line($result, 'special_date');
        $this->assertNotNull($special);
        $this->assertSame("New Year's Eve", $special['label']);
        $this->assertEqualsWithDelta(1.5, $special['factor'], 0.0001);
        $this->assertSame(PriceEstimatorService::roundTo(2000 * 1.05 * 0.95 * 1.5, 50), $result['estimate']);
    }

    public function test_one_off_special_date_multiplier(): void
    {
        $this->seedWeddingHistory();
        $this->service->saveSettings($this->band, ['special_dates' => [
            ['date' => '2027-05-15', 'label' => 'Jazz Fest weekend', 'multiplier' => 1.2],
        ]]);

        $match = $this->service->estimate($this->band, ['event_type_id' => $this->wedding->id, 'date' => '2027-05-15']);
        $this->assertSame('Jazz Fest weekend', $this->line($match, 'special_date')['label']);

        $noMatch = $this->service->estimate($this->band, ['event_type_id' => $this->wedding->id, 'date' => '2028-05-15']);
        $this->assertNull($this->line($noMatch, 'special_date'));
    }

    public function test_short_notice_multiplier(): void
    {
        $this->seedWeddingHistory();
        $this->service->saveSettings($this->band, ['short_notice_multiplier' => 1.2]);

        $soon = $this->service->estimate($this->band, ['event_type_id' => $this->wedding->id, 'date' => '2026-10-21']);
        $line = $this->line($soon, 'short_notice');
        $this->assertNotNull($line);
        $this->assertSame('Short notice (10 days out)', $line['label']);

        $later = $this->service->estimate($this->band, ['event_type_id' => $this->wedding->id, 'date' => '2027-05-15']);
        $this->assertNull($this->line($later, 'short_notice'));
    }

    // ── Fees ─────────────────────────────────────────────────────────────

    public function test_travel_fee_from_client_supplied_distance(): void
    {
        $this->seedWeddingHistory();

        // (100 − 50 free) × 2 × $2/mi = 200
        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'distance_miles' => 100,
        ]);

        $travel = $this->line($result, 'travel');
        $this->assertSame('fee', $travel['type']);
        $this->assertEqualsWithDelta(200.0, $travel['amount'], 0.01);
        $this->assertSame(100.0, $result['distance_miles']);
        $this->assertSame(2400.0, $result['estimate']);
        $this->assertSame(1300.0, $result['low']);  // 1100 + 200
        $this->assertSame(3500.0, $result['high']); // 3300 + 200
        $this->assertNull($this->line($result, 'lodging'));
    }

    public function test_no_travel_fee_inside_free_radius(): void
    {
        $this->seedWeddingHistory();

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'distance_miles' => 20,
        ]);

        $this->assertNull($this->line($result, 'travel'));
        $this->assertSame(2200.0, $result['estimate']);
    }

    public function test_lodging_fee_beyond_threshold(): void
    {
        $this->seedWeddingHistory();
        $this->service->saveSettings($this->band, ['lodging_fee' => 400]);

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'distance_miles' => 200,
        ]);

        $this->assertEqualsWithDelta(600.0, $this->line($result, 'travel')['amount'], 0.01); // 150 × 2 × 2
        $this->assertEqualsWithDelta(400.0, $this->line($result, 'lodging')['amount'], 0.01);
        $this->assertSame(3200.0, $result['estimate']);
    }

    public function test_add_ons_are_added_as_fees(): void
    {
        $this->seedWeddingHistory();

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'add_ons' => ['production', 'nonexistent'],
        ]);

        $line = $this->line($result, 'addon:production');
        $this->assertSame('Production (sound & lights)', $line['label']);
        $this->assertEqualsWithDelta(1500.0, $line['amount'], 0.01);
        $this->assertSame(3700.0, $result['estimate']);
    }

    public function test_distance_lookup_uses_google_and_caches(): void
    {
        $this->seedWeddingHistory();

        \GoogleMaps::shouldReceive('load')->once()->with('distancematrix')->andReturnSelf();
        \GoogleMaps::shouldReceive('setParamByKey')->andReturnSelf();
        \GoogleMaps::shouldReceive('get')->once()->andReturn(json_encode([
            'rows' => [['elements' => [[
                'status' => 'OK',
                'distance' => ['text' => '100 mi', 'value' => 160934.4],
                'duration' => ['text' => '1 hour 40 mins', 'value' => 6000],
            ]]]],
        ]));

        $input = [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'venue_address' => '6500 Magazine St, New Orleans, LA',
        ];

        $first = $this->service->estimate($this->band, $input);
        $second = $this->service->estimate($this->band, $input); // served from cache

        $this->assertSame(100.0, $first['distance_miles']);
        $this->assertSame(100.0, $second['distance_miles']);
        $this->assertEqualsWithDelta(200.0, $this->line($first, 'travel')['amount'], 0.01);
        $this->assertNotContains('distance_unavailable', $this->warningCodes($first));
    }

    public function test_distance_failure_warns_and_adds_no_fee(): void
    {
        $this->seedWeddingHistory();

        \GoogleMaps::shouldReceive('load')->andReturnSelf();
        \GoogleMaps::shouldReceive('setParamByKey')->andReturnSelf();
        \GoogleMaps::shouldReceive('get')->andReturn(json_encode([
            'rows' => [['elements' => [['status' => 'ZERO_RESULTS']]]],
        ]));

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'venue_address' => 'Nowhere',
        ]);

        $this->assertNull($result['distance_miles']);
        $this->assertNull($this->line($result, 'travel'));
        $this->assertContains('distance_unavailable', $this->warningCodes($result));
        $this->assertSame(2200.0, $result['estimate']);
    }

    public function test_distance_exception_is_handled(): void
    {
        $this->seedWeddingHistory();

        \GoogleMaps::shouldReceive('load')->andThrow(new \RuntimeException('network down'));

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'venue_address' => 'Somewhere',
        ]);

        $this->assertContains('distance_unavailable', $this->warningCodes($result));
    }

    public function test_missing_band_address_warns_without_calling_google(): void
    {
        $this->seedWeddingHistory();
        $this->band->update(['address' => null]);

        \GoogleMaps::shouldReceive('load')->never();

        $result = $this->service->estimate($this->band->fresh(), [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'venue_address' => '6500 Magazine St, New Orleans, LA',
        ]);

        $this->assertContains('band_address_missing', $this->warningCodes($result));
        $this->assertNull($result['distance_miles']);
    }

    // ── Rounding, warnings, comparables ──────────────────────────────────

    public function test_rounding_respects_round_to(): void
    {
        $this->seedWeddingHistory();
        $this->service->saveSettings($this->band, ['round_to' => 500]);

        // 2000 × 1.1 × 1.3 = 2860 → 3000
        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'duration_hours' => 6,
        ]);

        $this->assertSame(3000.0, $result['estimate']);
    }

    public function test_date_conflict_warning(): void
    {
        $this->seedWeddingHistory();
        $existing = $this->makeBooking($this->wedding, 2000, '2027-05-15');

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
        ]);
        $this->assertContains('date_conflict', $this->warningCodes($result));
        $this->assertSame('You already have a booking on May 15, 2027.', collect($result['warnings'])->firstWhere('code', 'date_conflict')['message']);

        $excluded = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'exclude_booking_id' => $existing->id,
        ]);
        $this->assertNotContains('date_conflict', $this->warningCodes($excluded));
    }

    public function test_cancelled_booking_does_not_cause_date_conflict(): void
    {
        $this->seedWeddingHistory();
        $this->makeBooking($this->wedding, 2000, '2027-05-15', status: 'cancelled');

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
        ]);

        $this->assertNotContains('date_conflict', $this->warningCodes($result));
    }

    public function test_comparables_rank_same_month_first_then_most_recent(): void
    {
        $this->seedWeddingHistory();
        $this->makeBooking($this->wedding, 1500, '2026-09-05');
        $this->makeBooking($this->wedding, 1500, '2026-08-01');
        $this->makeBooking($this->wedding, 1500, '2025-12-06');

        $result = $this->service->estimate($this->band, [
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
        ]);

        $dates = array_column($result['comparables'], 'date');
        $this->assertSame(['2026-05-16', '2026-05-09', '2026-05-02', '2026-09-05', '2026-08-01'], $dates);

        $first = $result['comparables'][0];
        $this->assertSame(3300.0, $first['price']);
        $this->assertSame('Venue 2026-05-16', $first['venue_name']);
        $this->assertArrayHasKey('booking_id', $first);
        $this->assertArrayHasKey('name', $first);
    }

    public function test_primary_event_is_the_earliest_event(): void
    {
        $this->service->saveSettings($this->band, ['annual_increase_pct' => 0]);
        $booking = $this->makeBooking($this->wedding, 1100, '2026-05-02');
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'event_type_id' => $this->wedding->id,
            'date' => '2026-07-04',
        ]);

        $rows = $this->service->historyRows($this->band);

        $this->assertCount(1, $rows);
        $this->assertSame('2026-05-02', $rows->first()['date']->toDateString());
        $this->assertSame(1100.0, $rows->first()['price']);
    }

    public function test_history_summary_per_event_type(): void
    {
        $this->seedWeddingHistory();
        $this->makeBooking($this->barGig, 800, '2026-05-02');

        $summary = $this->service->historySummary($this->band);

        $this->assertCount(2, $summary);
        $wedding = collect($summary)->firstWhere('event_type_id', $this->wedding->id);
        $this->assertSame(3, $wedding['sample_size']);
        $this->assertEqualsWithDelta(2000.0, $wedding['baseline'], 0.01);
    }
}
