<?php

namespace Tests\Feature;

use App\Models\BandPayoutConfig;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EventShowUserPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_payout_is_computed_fresh_not_read_from_the_stale_cache(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 1000]);
        $config = BandPayoutConfig::create(['band_id' => $band->id, 'name' => 'Chosen', 'is_active' => true]);

        $eventType = EventTypes::factory()->create();
        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => $eventType->id,
        ]);

        // Poison the cache: if the page read calculation_result, the user
        // would see this number. The fresh estimate must win.
        Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'base_amount' => 100000,
            'adjusted_amount' => 100000,
            'payout_config_id' => $config->id,
            'calculation_result' => [
                'member_payouts' => [
                    ['user_id' => $user->id, 'name' => $user->name, 'amount' => 12345.67],
                ],
            ],
        ]);

        // Ground truth: the booking-details estimate (fresh, shared estimator).
        $bookingPage = $this->actingAs($user)
            ->get(route('Booking Details', [$band, $booking]));
        $bookingProps = AssertableInertia::fromTestResponse($bookingPage)->toArray()['props'];
        $freshAmount = collect($bookingProps['payoutResult']['member_payouts'] ?? [])
            ->firstWhere('user_id', $user->id)['amount'] ?? 0.0;

        $this->actingAs($user)
            ->get(route('events.show', $event->key))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('userPayout', fn ($v) => (float) $v === round($freshAmount, 2)
                    && (float) $v !== 12345.67));
    }
}
