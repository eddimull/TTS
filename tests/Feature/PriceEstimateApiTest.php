<?php

namespace Tests\Feature;

use App\Models\BandApiToken;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use App\Services\PriceEstimatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Developer API (band API token): POST /api/price-estimate.
 */
class PriceEstimateApiTest extends TestCase
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

        // Deliberately no ApiPermissionsSeeder: api:read-pricing must come
        // from the migration so production gets it.

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

    private function makeToken(Bands $band, array $permissions): string
    {
        $plain = Str::random(60);
        $token = BandApiToken::create([
            'band_id' => $band->id,
            'token' => hash('sha256', $plain),
            'name' => 'Test Token',
            'is_active' => true,
        ]);
        if ($permissions !== []) {
            $token->givePermissionTo($permissions);
        }

        return $plain;
    }

    private function seedHistory(Bands $band, array $rows): array
    {
        $ids = [];
        foreach ($rows as [$date, $price]) {
            $booking = Bookings::factory()->create([
                'band_id' => $band->id,
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
            $ids[] = $booking->id;
        }

        return $ids;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'event_type_id' => $this->wedding->id,
            'date' => '2027-05-15',
            'start_time' => '19:00',
            'end_time' => '23:00',
            'distance_miles' => 100,
        ], $overrides);
    }

    public function test_permission_is_created_by_migration(): void
    {
        $this->assertTrue(
            Permission::where('name', 'api:read-pricing')->where('guard_name', 'api_token')->exists()
        );
    }

    public function test_returns_estimate_with_read_pricing_permission(): void
    {
        $this->seedHistory($this->band, [['2026-05-02', 1100], ['2026-05-09', 2200], ['2026-05-16', 3300]]);
        $token = $this->makeToken($this->band, ['api:read-pricing']);

        $response = $this->withToken($token)->postJson('/api/price-estimate', $this->payload());

        $response->assertOk()
            ->assertJsonStructure([
                'estimate', 'low', 'high', 'confidence', 'baseline_source', 'sample_size',
                'baseline', 'duration_hours', 'distance_miles',
                'breakdown' => [['key', 'label', 'type', 'factor', 'amount']],
                'warnings',
                'comparables' => [['booking_id', 'name', 'date', 'price', 'venue_name']],
            ])
            ->assertJsonPath('baseline_source', 'history')
            ->assertJsonPath('sample_size', 3)
            ->assertJsonPath('distance_miles', 100.0);

        $this->assertMatchesRegularExpression('/"estimate":\d+\.\d+/', $response->getContent());
    }

    public function test_response_body_is_identical_to_mobile_endpoint(): void
    {
        $this->seedHistory($this->band, [['2026-05-02', 1100], ['2026-05-09', 2200], ['2026-05-16', 3300]]);
        $apiToken = $this->makeToken($this->band, ['api:read-pricing']);
        $sanctum = $this->owner->createToken('test-device')->plainTextToken;

        $developer = $this->withToken($apiToken)
            ->postJson('/api/price-estimate', $this->payload())
            ->assertOk()
            ->getContent();

        $this->app['auth']->forgetGuards();

        $mobile = $this->withToken($sanctum)
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->postJson("/api/mobile/bands/{$this->band->id}/price-estimate", $this->payload())
            ->assertOk()
            ->getContent();

        $this->assertSame($mobile, $developer);
    }

    public function test_forbidden_without_read_pricing_permission(): void
    {
        foreach (['api:read-bookings', 'api:write-bookings', 'api:read-events'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api_token']);
        }
        $token = $this->makeToken($this->band, ['api:read-bookings', 'api:write-bookings', 'api:read-events']);

        $this->withToken($token)
            ->postJson('/api/price-estimate', $this->payload())
            ->assertForbidden()
            ->assertJsonPath('required_permission', 'api:read-pricing');
    }

    public function test_unauthorized_without_token(): void
    {
        $this->postJson('/api/price-estimate', $this->payload())
            ->assertUnauthorized();
    }

    public function test_unauthorized_with_invalid_token(): void
    {
        $this->withToken('not-a-real-token')
            ->postJson('/api/price-estimate', $this->payload())
            ->assertUnauthorized();
    }

    public function test_unauthorized_with_inactive_token(): void
    {
        $token = $this->makeToken($this->band, ['api:read-pricing']);
        BandApiToken::where('band_id', $this->band->id)->update(['is_active' => false]);

        $this->withToken($token)
            ->postJson('/api/price-estimate', $this->payload())
            ->assertUnauthorized();
    }

    public function test_validates_input(): void
    {
        $token = $this->makeToken($this->band, ['api:read-pricing']);

        $this->withToken($token)
            ->postJson('/api/price-estimate', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_type_id', 'date']);

        $this->withToken($token)
            ->postJson('/api/price-estimate', [
                'event_type_id' => 999999,
                'date' => '05/15/2027',
                'start_time' => '7pm',
                'duration_hours' => 48,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_type_id', 'date', 'start_time', 'duration_hours']);
    }

    public function test_without_history_returns_null_estimate(): void
    {
        $token = $this->makeToken($this->band, ['api:read-pricing']);

        $this->withToken($token)
            ->postJson('/api/price-estimate', ['event_type_id' => $this->wedding->id, 'date' => '2027-05-15'])
            ->assertOk()
            ->assertJsonPath('estimate', null)
            ->assertJsonPath('warnings.0.code', 'no_baseline');
    }

    public function test_estimate_is_always_for_the_tokens_band(): void
    {
        $otherBand = Bands::factory()->create();
        // Other band: manual base rate, history, and a booking on the requested date.
        app(PriceEstimatorService::class)->saveSettings($otherBand, [
            'base_rates' => [(string) $this->wedding->id => 9000],
        ]);
        $otherIds = $this->seedHistory($otherBand, [['2026-05-02', 9000], ['2026-05-09', 9500], ['2027-05-15', 9900]]);

        $ownIds = $this->seedHistory($this->band, [['2026-05-02', 1100], ['2026-05-09', 2200], ['2026-05-16', 3300]]);
        $token = $this->makeToken($this->band, ['api:read-pricing']);

        // Attempts to point at the other band are ignored — the token's band is the band.
        $response = $this->withToken($token)
            ->postJson('/api/price-estimate', $this->payload([
                'band_id' => $otherBand->id,
                'band' => $otherBand->id,
                'authenticated_band' => $otherBand->id,
            ]))
            ->assertOk()
            ->assertJsonPath('baseline_source', 'history')
            ->assertJsonPath('sample_size', 3);

        $comparableIds = array_column($response->json('comparables'), 'booking_id');
        $this->assertEqualsCanonicalizing($ownIds, $comparableIds);
        $this->assertEmpty(array_intersect($otherIds, $comparableIds));
        $this->assertNotContains('date_conflict', array_column($response->json('warnings'), 'code'));

        // And the other band's token sees the other band's pricing.
        $otherToken = $this->makeToken($otherBand, ['api:read-pricing']);
        $this->withToken($otherToken)
            ->postJson('/api/price-estimate', $this->payload())
            ->assertOk()
            ->assertJsonPath('baseline_source', 'manual')
            ->assertJsonPath('warnings.0.code', 'date_conflict');
    }
}
