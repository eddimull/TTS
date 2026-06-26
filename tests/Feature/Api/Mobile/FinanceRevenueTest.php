<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Payments;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceRevenueTest extends TestCase
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

        // Band member with the read:bookings ability — the finances routes are
        // gated by `mobile.band:read:bookings`.
        BandMembers::create(['band_id' => $this->band->id, 'user_id' => $this->member->id]);
        $this->memberToken = $this->member
            ->createToken('test-device', ['read:bookings'])
            ->plainTextToken;
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
     * Create a payment on a band. $dollars is multiplied by 100 by the Price
     * cast, so $dollars=1500 is stored as 150000 cents.
     */
    private function payment(Bands $band, ?int $dollars, ?string $date): Payments
    {
        $booking = Bookings::factory()->for($band, 'band')->create();

        return Payments::factory()->create([
            'band_id' => $band->id,
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'amount' => $dollars,
            'date' => $date,
        ]);
    }

    public function test_returns_revenue_grouped_by_year_in_cents(): void
    {
        // 2026: 150000 + 50000 = 200000 cents
        $this->payment($this->band, 1500, '2026-03-01');
        $this->payment($this->band, 500, '2026-09-15');
        // 2025: 98000 cents
        $this->payment($this->band, 980, '2025-07-04');

        $res = $this->withHeaders($this->headers($this->memberToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/revenue");

        $res->assertOk();
        $this->assertSame(
            [
                ['year' => 2026, 'total' => 200000],
                ['year' => 2025, 'total' => 98000],
            ],
            $res->json('revenue'),
        );
    }

    public function test_excludes_payments_with_null_date(): void
    {
        $this->payment($this->band, 100, '2026-01-01');   // 10000 cents
        $this->payment($this->band, 999, null);            // pending, no date

        $res = $this->withHeaders($this->headers($this->memberToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/revenue");

        $res->assertOk();
        $revenue = $res->json('revenue');
        $this->assertCount(1, $revenue);
        $this->assertSame([['year' => 2026, 'total' => 10000]], $revenue);
    }

    public function test_scopes_to_requested_band_only(): void
    {
        $other = Bands::factory()->create();

        $this->payment($this->band, 100, '2026-01-01');  // 10000 cents
        $this->payment($other, 700, '2026-01-01');       // belongs to a different band

        $res = $this->withHeaders($this->headers($this->memberToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/revenue");

        $res->assertOk();
        $this->assertSame([['year' => 2026, 'total' => 10000]], $res->json('revenue'));
    }

    public function test_requires_band_access(): void
    {
        // A user with the read:bookings ability but NO membership of $this->band.
        $stranger = User::factory()->create();
        $strangerToken = $stranger
            ->createToken('test-device', ['read:bookings'])
            ->plainTextToken;

        $this->withHeaders($this->headers($strangerToken))
            ->getJson("/api/mobile/bands/{$this->band->id}/finances/revenue")
            ->assertForbidden();
    }
}
