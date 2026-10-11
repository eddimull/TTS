<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandPricingSettings;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PriceEstimatorTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private EventTypes $wedding;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00'));

        $this->owner = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->wedding = EventTypes::factory()->create(['name' => 'Wedding']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedHistory(): void
    {
        foreach ([['2026-05-02', 1100], ['2026-05-09', 2200], ['2026-05-16', 3300]] as [$date, $price]) {
            $booking = Bookings::factory()->create([
                'band_id' => $this->band->id,
                'event_type_id' => $this->wedding->id,
                'price' => $price,
                'status' => 'confirmed',
            ]);
            Events::factory()->create([
                'eventable_type' => Bookings::class,
                'eventable_id' => $booking->id,
                'event_type_id' => $this->wedding->id,
                'date' => $date,
                'start_time' => '19:00',
                'end_time' => '23:00',
            ]);
        }
    }

    private function asUser(User $user): static
    {
        $token = $user->createToken('test-device')->plainTextToken;

        return $this->withToken($token)->withHeaders(['X-Band-ID' => $this->band->id]);
    }

    private function member(): User
    {
        $member = User::factory()->create();
        $this->band->members()->create(['user_id' => $member->id]);

        return $member;
    }

    // ── POST price-estimate ──────────────────────────────────────────────

    public function test_estimate_requires_authentication(): void
    {
        $this->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", [])
            ->assertUnauthorized();
    }

    public function test_estimate_requires_band_header(): void
    {
        $token = $this->owner->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", [
                'event_type_id' => $this->wedding->id,
                'date' => '2027-05-15',
            ])
            ->assertStatus(422);
    }

    public function test_estimate_forbidden_for_non_owner_member(): void
    {
        $this->asUser($this->member())
            ->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", [
                'event_type_id' => $this->wedding->id,
                'date' => '2027-05-15',
            ])
            ->assertForbidden();
    }

    public function test_estimate_forbidden_for_non_member(): void
    {
        $this->asUser(User::factory()->create())
            ->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", [
                'event_type_id' => $this->wedding->id,
                'date' => '2027-05-15',
            ])
            ->assertForbidden();
    }

    public function test_estimate_validates_input(): void
    {
        $this->asUser($this->owner)
            ->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", [
                'start_time' => '7pm',
                'distance_miles' => -5,
                'add_ons' => 'production',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_type_id', 'date', 'start_time', 'distance_miles', 'add_ons']);
    }

    public function test_estimate_rejects_unknown_event_type_and_bad_date(): void
    {
        $this->asUser($this->owner)
            ->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", [
                'event_type_id' => 999999,
                'date' => '05/15/2027',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_type_id', 'date']);
    }

    public function test_owner_gets_estimate_matching_contract(): void
    {
        $this->seedHistory();

        $response = $this->asUser($this->owner)
            ->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", [
                'event_type_id' => $this->wedding->id,
                'date' => '2027-05-15',
                'start_time' => '19:00',
                'end_time' => '23:00',
                'duration_hours' => null,
                'venue_address' => null,
                'distance_miles' => 100,
                'add_ons' => ['production'],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'estimate', 'low', 'high', 'confidence', 'baseline_source', 'sample_size',
                'baseline', 'duration_hours', 'distance_miles',
                'breakdown' => [['key', 'label', 'type', 'factor', 'amount']],
                'warnings',
                'comparables' => [['booking_id', 'name', 'date', 'price', 'venue_name']],
            ])
            ->assertJsonPath('baseline_source', 'history')
            ->assertJsonPath('confidence', 'medium')
            ->assertJsonPath('sample_size', 3)
            ->assertJsonPath('distance_miles', 100.0);

        // Annual inflation is on by default, so assert loosely on the number
        // and exactly on the structure of the breakdown.
        $keys = array_column($response->json('breakdown'), 'key');
        $this->assertSame(['baseline', 'season', 'day_of_week', 'travel', 'addon:production'], $keys);
        $this->assertIsNumeric($response->json('estimate'));
        $this->assertEquals(0, fmod($response->json('estimate'), 50));
        $this->assertLessThanOrEqual($response->json('estimate'), $response->json('low'));
        $this->assertGreaterThanOrEqual($response->json('estimate'), $response->json('high'));

        // Money is serialized as JSON numbers with a decimal point.
        $this->assertMatchesRegularExpression('/"estimate":\d+\.\d+/', $response->getContent());
    }

    public function test_estimate_without_history_returns_200_with_null_estimate(): void
    {
        $this->asUser($this->owner)
            ->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", [
                'event_type_id' => $this->wedding->id,
                'date' => '2027-05-15',
            ])
            ->assertOk()
            ->assertJsonPath('estimate', null)
            ->assertJsonPath('warnings.0.code', 'no_baseline');
    }

    // ── pricing-settings ─────────────────────────────────────────────────

    public function test_settings_get_returns_defaults_event_types_and_history(): void
    {
        $this->seedHistory();

        $response = $this->asUser($this->owner)
            ->getJson("/api/mobile/bands/{$this->band->id}/pricing-settings");

        $response->assertOk()
            ->assertJsonStructure([
                'settings' => [
                    'season_multipliers', 'day_of_week_multipliers', 'special_dates', 'standard_hours',
                    'standard_hours_by_type', 'overtime_pct_per_hour', 'min_duration_factor',
                    'short_notice_days', 'short_notice_multiplier', 'annual_increase_pct',
                    'free_radius_miles', 'per_mile_rate', 'lodging_threshold_miles', 'lodging_fee',
                    'add_ons', 'base_rates', 'round_to',
                ],
                'event_types' => [['id', 'name']],
                'history' => [['event_type_id', 'sample_size', 'baseline']],
            ])
            ->assertJsonPath('settings.season_multipliers.5', 1.1)
            ->assertJsonPath('settings.day_of_week_multipliers.0', 0.9)
            ->assertJsonPath('settings.add_ons.0.key', 'production')
            ->assertJsonPath('history.0.event_type_id', $this->wedding->id)
            ->assertJsonPath('history.0.sample_size', 3);

        // Keyed maps must serialize as JSON objects, even when empty.
        $content = $response->getContent();
        $this->assertStringContainsString('"day_of_week_multipliers":{"0":', $content);
        $this->assertStringContainsString('"base_rates":{}', $content);
        $this->assertStringContainsString('"standard_hours_by_type":{}', $content);
    }

    public function test_settings_forbidden_for_non_owner_member(): void
    {
        $member = $this->member();

        $this->asUser($member)
            ->getJson("/api/mobile/bands/{$this->band->id}/pricing-settings")
            ->assertForbidden();

        $this->asUser($member)
            ->putJson("/api/mobile/bands/{$this->band->id}/pricing-settings", ['settings' => ['round_to' => 100]])
            ->assertForbidden();
    }

    public function test_settings_put_partially_merges(): void
    {
        $response = $this->asUser($this->owner)
            ->putJson("/api/mobile/bands/{$this->band->id}/pricing-settings", [
                'settings' => [
                    'round_to' => 100,
                    'season_multipliers' => ['6' => 1.2],
                    'base_rates' => [(string) $this->wedding->id => 9000],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('settings.round_to', 100.0)
            ->assertJsonPath('settings.season_multipliers.6', 1.2)
            ->assertJsonPath('settings.season_multipliers.5', 1.1)
            ->assertJsonPath("settings.base_rates.{$this->wedding->id}", 9000.0)
            ->assertJsonPath('settings.per_mile_rate', 2.0);

        $stored = BandPricingSettings::where('band_id', $this->band->id)->first();
        $this->assertNotNull($stored);
        $this->assertEquals(100, $stored->settings['round_to']);
        $this->assertArrayNotHasKey('per_mile_rate', $stored->settings);

        // A second partial update keeps earlier keys.
        $this->asUser($this->owner)
            ->putJson("/api/mobile/bands/{$this->band->id}/pricing-settings", [
                'settings' => ['per_mile_rate' => 3.5],
            ])
            ->assertOk()
            ->assertJsonPath('settings.round_to', 100.0)
            ->assertJsonPath('settings.per_mile_rate', 3.5);

        // Manual base rate now drives the estimate.
        $this->asUser($this->owner)
            ->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", [
                'event_type_id' => $this->wedding->id,
                'date' => '2027-05-15',
            ])
            ->assertOk()
            ->assertJsonPath('baseline_source', 'manual')
            ->assertJsonPath('estimate', 9900.0);
    }

    public function test_settings_put_validates(): void
    {
        $this->asUser($this->owner)
            ->putJson("/api/mobile/bands/{$this->band->id}/pricing-settings", [
                'settings' => [
                    'season_multipliers' => ['1' => -1],
                    'special_dates' => [['date' => '13-45', 'multiplier' => 1.5]],
                    'add_ons' => [['key' => 'has spaces', 'label' => 'X', 'amount' => 10]],
                    'round_to' => 0,
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'settings.season_multipliers.1',
                'settings.special_dates.0.date',
                'settings.add_ons.0.key',
                'settings.round_to',
            ]);

        $this->asUser($this->owner)
            ->putJson("/api/mobile/bands/{$this->band->id}/pricing-settings", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }
}
