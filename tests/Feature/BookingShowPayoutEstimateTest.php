<?php

namespace Tests\Feature;

use App\Models\BandPayoutConfig;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BookingShowPayoutEstimateTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_estimate_uses_the_bookings_stored_config_not_the_active_one(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 1000]);

        $active = BandPayoutConfig::create(['band_id' => $band->id, 'name' => 'Active', 'is_active' => true]);
        $stored = BandPayoutConfig::create(['band_id' => $band->id, 'name' => 'Chosen', 'is_active' => false]);

        Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'base_amount' => 100000,
            'adjusted_amount' => 100000,
            'payout_config_id' => $stored->id,
        ]);

        // The booking-details estimate must agree with the Payout page: the
        // stored per-booking config wins over the band's active one.
        $this->actingAs($user)
            ->get(route('Booking Details', [$band, $booking]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('payoutConfig.id', $stored->id));
    }

    public function test_show_estimate_falls_back_to_active_config_without_a_payout(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 1000]);

        $active = BandPayoutConfig::create(['band_id' => $band->id, 'name' => 'Active', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('Booking Details', [$band, $booking]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('payoutConfig.id', $active->id));
    }
}
