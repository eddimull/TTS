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
}
