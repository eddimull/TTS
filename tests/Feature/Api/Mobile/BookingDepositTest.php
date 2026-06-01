<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDepositTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a user that owns a band, plus a booking for that band, and a
     * personal-access token for mobile auth. Mirrors helpers used elsewhere
     * under tests/Feature/Api/Mobile.
     */
    private function makeOwnedBooking(array $bookingOverrides = []): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create(array_merge([
            'band_id' => $band->id,
            'price'   => '1000.00',
        ], $bookingOverrides));

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'booking', 'token');
    }

    private function patchBooking(string $token, Bands $band, Bookings $booking, array $payload)
    {
        return $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}", $payload);
    }

    public function test_mobile_update_accepts_valid_deposit_amount(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeOwnedBooking();

        $this->patchBooking($token, $band, $booking, [
            'deposit_type'  => 'amount',
            'deposit_value' => '450.00',
        ])->assertOk();

        $this->assertSame('amount', $booking->fresh()->deposit_type);
        $this->assertSame('450.00', (string) $booking->fresh()->deposit_value);
    }

    public function test_mobile_update_rejects_percent_above_100(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeOwnedBooking();

        $this->patchBooking($token, $band, $booking, [
            'deposit_type'  => 'percent',
            'deposit_value' => '150',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('deposit_value');
    }

    public function test_mobile_update_rejects_amount_above_price(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeOwnedBooking();

        $this->patchBooking($token, $band, $booking, [
            'deposit_type'  => 'amount',
            'deposit_value' => '2000',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('deposit_value');
    }

    public function test_mobile_update_rejects_deposit_when_contract_signed(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeOwnedBooking();

        Contracts::factory()->create([
            'contractable_id'   => $booking->id,
            'contractable_type' => Bookings::class,
            'status'            => 'completed',
        ]);

        $this->patchBooking($token, $band, $booking, [
            'deposit_type'  => 'amount',
            'deposit_value' => '600',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('deposit_type');
    }

    public function test_mobile_booking_show_response_includes_deposit_fields(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeOwnedBooking([
            'price'          => '1000.00',
            'deposit_type'   => 'percent',
            'deposit_value'  => '30.00',
        ]);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}");

        $response->assertOk()
            ->assertJsonPath('booking.deposit_type', 'percent')
            ->assertJsonPath('booking.deposit_value', '30.00')
            ->assertJsonPath('booking.expected_deposit_amount', '300.00');
    }
}
