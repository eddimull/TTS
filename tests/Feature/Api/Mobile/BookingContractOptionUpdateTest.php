<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingContractOptionUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(User $user, string $contractStatus = 'pending'): Bookings
    {
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'status'          => 'draft',
            'contract_option' => 'default',
        ]);
        $booking->contract()->create(['author_id' => $user->id, 'status' => $contractStatus]);

        return $booking;
    }

    public function test_contract_option_updates_when_contract_not_sent(): void
    {
        $user    = User::factory()->create();
        $booking = $this->makeBooking($user);
        $token   = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $booking->band_id])
            ->patchJson("/api/mobile/bands/{$booking->band_id}/bookings/{$booking->id}", [
                'contract_option' => 'external',
            ])
            ->assertOk()
            ->assertJsonPath('booking.contract_option', 'external');

        $this->assertSame('external', $booking->fresh()->contract_option);
    }

    public function test_contract_option_rejected_once_contract_sent(): void
    {
        $user    = User::factory()->create();
        $booking = $this->makeBooking($user, contractStatus: 'sent');
        $token   = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $booking->band_id])
            ->patchJson("/api/mobile/bands/{$booking->band_id}/bookings/{$booking->id}", [
                'contract_option' => 'none',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_option']);

        $this->assertSame('default', $booking->fresh()->contract_option);
    }

    public function test_contract_option_value_validated(): void
    {
        $user    = User::factory()->create();
        $booking = $this->makeBooking($user);
        $token   = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $booking->band_id])
            ->patchJson("/api/mobile/bands/{$booking->band_id}/bookings/{$booking->id}", [
                'contract_option' => 'verbal',
            ])
            ->assertStatus(422);
    }
}
