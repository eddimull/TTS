<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\BandPayoutConfig;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\Payments;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTrendsTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;
    protected Bands $band;
    protected string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->create();
        $this->band = Bands::factory()->create();

        // Band member with the read:bookings permission — the finances routes are
        // gated by `mobile.band:read:bookings`, which re-checks the per-band
        // permission (not just the token ability), so the member must actually
        // hold read:bookings for this band.
        BandMembers::create(['band_id' => $this->band->id, 'user_id' => $this->member->id]);
        setPermissionsTeamId($this->band->id);
        $this->member->givePermissionTo('read:bookings');
        setPermissionsTeamId(0);
        $this->memberToken = $this->member
            ->createToken('test-device', ['read:bookings'])
            ->plainTextToken;

        // Active percentage payout config so net_amount (band cut) is populated.
        BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Default',
            'is_active' => true,
            'band_cut_type' => 'percentage',
            'band_cut_value' => 50,
        ]);
    }

    private function headers(string $token, ?int $bandId = null): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'X-Band-ID' => $bandId ?? $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Create a booking on a band with a known price (dollars), a primary event
     * on $eventDate (drives start_date), and optional fully/partially paid state.
     *
     * @param  int    $priceDollars  Dollar price; stored as cents by the Price cast.
     * @param  int    $paidDollars   Dollars paid; a single 'paid' payment is recorded.
     */
    private function booking(
        Bands $band,
        int $priceDollars,
        string $eventDate,
        int $paidDollars = 0,
        string $status = 'confirmed',
        ?string $createdAt = null,
    ): Bookings {
        $booking = Bookings::factory()->for($band, 'band')->create([
            'price' => $priceDollars,
            'status' => $status,
        ]);

        if ($createdAt !== null) {
            $booking->forceFill(['created_at' => $createdAt])->save();
        }

        // Primary event drives the derived start_date accessor (first event date).
        Events::factory()->forBand($band)->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => $eventDate,
        ]);

        if ($paidDollars > 0) {
            Payments::factory()->create([
                'band_id' => $band->id,
                'payable_type' => Bookings::class,
                'payable_id' => $booking->id,
                'amount' => $paidDollars,
                'status' => 'paid',
                'date' => $eventDate,
            ]);
        }

        return $booking->refresh();
    }

    public function test_buckets_by_month_in_cents_and_excludes_cancelled(): void
    {
        // $1500 fully paid in March 2026 → paid = 150000 cents, net = 50% = 75000.
        $this->booking($this->band, 1500, '2026-03-10', paidDollars: 1500);
        // $2000 unpaid in July 2026 → forecast/unpaid = 200000 cents, net = 100000.
        $this->booking($this->band, 2000, '2026-07-04', paidDollars: 0);
        // Cancelled booking that must be excluded entirely.
        $this->booking($this->band, 9999, '2026-05-01', paidDollars: 0, status: 'cancelled');

        $res = $this->withHeaders($this->headers($this->memberToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/trends?year=2026");

        $res->assertOk();
        $res->assertJsonPath('year', 2026);
        $res->assertJsonCount(12, 'months');

        // March (index 2): fully paid $1500.
        $res->assertJsonPath('months.2.month', 3);
        $res->assertJsonPath('months.2.paid', 150000);
        $res->assertJsonPath('months.2.unpaid', 0);
        $res->assertJsonPath('months.2.forecast', 150000);
        $res->assertJsonPath('months.2.net', 75000);
        $res->assertJsonPath('months.2.count', 1);

        // July (index 6): $2000 unpaid.
        $res->assertJsonPath('months.6.month', 7);
        $res->assertJsonPath('months.6.paid', 0);
        $res->assertJsonPath('months.6.unpaid', 200000);
        $res->assertJsonPath('months.6.forecast', 200000);
        $res->assertJsonPath('months.6.net', 100000);
        $res->assertJsonPath('months.6.count', 1);

        // May (index 4): cancelled booking excluded → zero-filled.
        $res->assertJsonPath('months.4.count', 0);
        $res->assertJsonPath('months.4.forecast', 0);

        // January (index 0): nothing → zero-filled.
        $res->assertJsonPath('months.0.month', 1);
        $res->assertJsonPath('months.0.count', 0);
        $res->assertJsonPath('months.0.paid', 0);
    }

    public function test_available_years_lists_distinct_booking_years_descending(): void
    {
        $this->booking($this->band, 1000, '2024-02-01', paidDollars: 1000);
        $this->booking($this->band, 1000, '2026-02-01', paidDollars: 0);
        $this->booking($this->band, 1000, '2025-08-01', paidDollars: 0);
        // Duplicate year should collapse.
        $this->booking($this->band, 1000, '2026-11-01', paidDollars: 0);

        // available_years is independent of the ?year filter.
        $res = $this->withHeaders($this->headers($this->memberToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/trends?year=2024");

        $res->assertOk();
        $this->assertSame([2026, 2025, 2024], $res->json('available_years'));
    }

    public function test_snapshot_date_limits_primary_series_by_created_at(): void
    {
        // Booking created before the snapshot → included in months.
        $this->booking(
            $this->band,
            1000,
            '2026-04-01',
            paidDollars: 1000,
            createdAt: '2026-01-15 00:00:00',
        );
        // Booking created AFTER the snapshot → excluded from snapshot months,
        // but present in current_months when comparing.
        $this->booking(
            $this->band,
            3000,
            '2026-06-01',
            paidDollars: 0,
            createdAt: '2026-09-20 00:00:00',
        );

        $res = $this->withHeaders($this->headers($this->memberToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/trends?year=2026&snapshot_date=2026-02-01&compare_with_current=1");

        $res->assertOk();
        $res->assertJsonPath('snapshot_date', '2026-02-01');

        // April booking is in the snapshot series; June booking is not.
        $res->assertJsonPath('months.3.paid', 100000);
        $res->assertJsonPath('months.3.count', 1);
        $res->assertJsonPath('months.5.count', 0);
        $res->assertJsonPath('months.5.forecast', 0);

        // current_months (unfiltered) includes the later June booking.
        $res->assertJsonPath('current_months.5.count', 1);
        $res->assertJsonPath('current_months.5.forecast', 300000);
        $res->assertJsonPath('current_months.3.count', 1);
    }

    public function test_compare_without_snapshot_omits_current_months(): void
    {
        $this->booking($this->band, 1000, '2026-04-01', paidDollars: 1000);

        $res = $this->withHeaders($this->headers($this->memberToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/trends?year=2026&compare_with_current=1");

        $res->assertOk();
        $this->assertNull($res->json('current_months'));
    }

    public function test_scopes_to_requested_band_only(): void
    {
        $other = Bands::factory()->create();
        BandPayoutConfig::create([
            'band_id' => $other->id,
            'name' => 'Other',
            'is_active' => true,
            'band_cut_type' => 'percentage',
            'band_cut_value' => 50,
        ]);

        $this->booking($this->band, 1000, '2026-04-01', paidDollars: 1000);
        // Belongs to a different band — must not leak.
        $this->booking($other, 5000, '2026-04-01', paidDollars: 5000);

        $res = $this->withHeaders($this->headers($this->memberToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/trends?year=2026");

        $res->assertOk();
        // April carries only this band's $1000 booking.
        $res->assertJsonPath('months.3.paid', 100000);
        $res->assertJsonPath('months.3.count', 1);
    }

    public function test_requires_band_access(): void
    {
        // A user with the read:bookings ability but NO membership of $this->band.
        $stranger = User::factory()->create();
        $strangerToken = $stranger
            ->createToken('test-device', ['read:bookings'])
            ->plainTextToken;

        $this->withHeaders($this->headers($strangerToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/trends?year=2026")
            ->assertForbidden();
    }
}
