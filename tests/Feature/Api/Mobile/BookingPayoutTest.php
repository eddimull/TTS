<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\BandPayoutConfig;
use App\Models\BandSubs;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPayoutTest extends TestCase
{
    use RefreshDatabase;

    private function setup_booking(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);
        $config = BandPayoutConfig::factory()->create([
            'band_id' => $band->id, 'is_active' => true,
            'band_cut_type' => 'percentage', 'band_cut_value' => 20,
        ]);
        $booking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 1000]);
        Events::factory()->create(['eventable_id' => $booking->id, 'eventable_type' => Bookings::class, 'value' => 1000]);
        $token = $user->createToken('test-device')->plainTextToken;
        return compact('user', 'band', 'booking', 'config', 'token');
    }

    private function headers(string $token, int $bandId): array
    {
        return ['Authorization' => "Bearer {$token}", 'X-Band-ID' => $bandId, 'Accept' => 'application/json'];
    }

    public function test_payout_show_returns_breakdown_structure(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        $response = $this->withHeaders($this->headers($token, $band->id))
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout");

        $response->assertOk()->assertJsonStructure([
            'payout' => ['id', 'base_amount', 'adjusted_amount', 'payout_config_id'],
            'config' => ['id', 'name', 'is_active'],
            'result' => ['total_amount', 'band_cut', 'distributable_amount', 'member_payouts', 'payment_group_payouts'],
            'adjustments',
            'events' => [['id', 'label', 'value', 'members']],
            'available_configs' => [['id', 'name', 'is_active']],
        ]);

        $response->assertJsonPath('config.is_active', true);
        // 20% of 1000 = 200; PHP returns an int here, assertJsonPath uses ===
        $response->assertJsonPath('result.band_cut', 200);
    }

    public function test_payout_show_forbidden_for_non_member(): void
    {
        ['band' => $band, 'booking' => $booking] = $this->setup_booking();
        $outsider = User::factory()->create();
        $token = $outsider->createToken('d')->plainTextToken;

        $this->withHeaders($this->headers($token, $band->id))
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout")
            ->assertForbidden();
    }

    public function test_payout_show_forbidden_for_sub(): void
    {
        ['band' => $band, 'booking' => $booking] = $this->setup_booking();

        // Create a sub: a user who appears in band_subs but NOT in band_owners/band_members
        $sub = User::factory()->create();
        BandSubs::create(['user_id' => $sub->id, 'band_id' => $band->id]);
        $token = $sub->createToken('sub-device')->plainTextToken;

        $this->withHeaders($this->headers($token, $band->id))
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout")
            ->assertForbidden();
    }

    public function test_store_adjustment_recalculates_adjusted_amount(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        $response = $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", [
                'amount' => -250, 'description' => 'Gas / travel', 'notes' => 'Reimbursed',
            ]);

        $response->assertCreated()->assertJsonStructure(['adjustment' => ['id', 'amount', 'description', 'notes']]);
        $this->assertSame('750.00', (string) $booking->fresh()->payout->adjusted_amount);
    }

    public function test_store_adjustment_validates_description_required(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();
        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", ['amount' => 10])
            ->assertStatus(422);
    }

    public function test_destroy_adjustment_recalculates(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();
        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", ['amount' => -250, 'description' => 'X']);
        $adjId = $booking->fresh()->payout->adjustments->first()->id;

        $this->withHeaders($this->headers($token, $band->id))
            ->deleteJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments/{$adjId}")
            ->assertOk();
        $this->assertSame('1000.00', (string) $booking->fresh()->payout->adjusted_amount);
    }

    public function test_destroy_adjustment_rejects_foreign_adjustment(): void
    {
        // booking1 belongs to the same band as booking2.  The band-membership
        // middleware passes for both routes (same user, same band).  The 403
        // must come exclusively from the payout-ownership guard:
        //   abort_unless($adjustment->payout_id === $payout->id, 403, …)
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        // Create a second booking for the SAME band and same owner.
        $booking2 = Bookings::factory()->create(['band_id' => $band->id, 'price' => 500]);
        Events::factory()->create([
            'eventable_id'   => $booking2->id,
            'eventable_type' => Bookings::class,
            'value'          => 500,
        ]);

        // Add an adjustment to booking #1's payout.
        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", [
                'amount'      => -100,
                'description' => 'Booking1 adjustment',
            ]);

        $adjId = $booking->fresh()->payout->adjustments->first()->id;

        // Ensure booking #2 has a payout record so the cross-ownership guard is
        // reached (not the earlier 404 "payout not found" guard).
        $this->withHeaders($this->headers($token, $band->id))
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking2->id}/payout")
            ->assertOk();

        // Attempt to delete booking #1's adjustment via booking #2's URL — must be 403.
        $this->withHeaders($this->headers($token, $band->id))
            ->deleteJson("/api/mobile/bands/{$band->id}/bookings/{$booking2->id}/payout/adjustments/{$adjId}")
            ->assertForbidden();

        // Adjustment must still exist in the DB.
        $this->assertDatabaseHas('payout_adjustments', ['id' => $adjId]);
    }

    public function test_store_adjustment_forbidden_for_sub(): void
    {
        ['band' => $band, 'booking' => $booking] = $this->setup_booking();

        $sub = User::factory()->create();
        BandSubs::create(['user_id' => $sub->id, 'band_id' => $band->id]);
        $token = $sub->createToken('sub-device')->plainTextToken;

        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", [
                'amount' => -100, 'description' => 'Should be blocked',
            ])
            ->assertForbidden();
    }

    public function test_destroy_adjustment_forbidden_for_sub(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->setup_booking();

        // Owner creates an adjustment first
        $this->withHeaders($this->headers($token, $band->id))
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments", [
                'amount' => -100, 'description' => 'Owner adjustment',
            ]);
        $adjId = $booking->fresh()->payout->adjustments->first()->id;

        // Sub attempts to delete it — use actingAs to avoid Sanctum token-cache
        // interference from the preceding owner request in this same test.
        $sub = User::factory()->create();
        BandSubs::create(['user_id' => $sub->id, 'band_id' => $band->id]);

        $this->actingAs($sub, 'sanctum')
            ->withHeaders(['X-Band-ID' => $band->id, 'Accept' => 'application/json'])
            ->deleteJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/payout/adjustments/{$adjId}")
            ->assertForbidden();
    }
}
