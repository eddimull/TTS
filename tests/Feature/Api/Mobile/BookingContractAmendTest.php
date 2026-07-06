<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingContractAmendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.pandadoc.api_key', 'fake-api-key');
    }

    private function makeBooking(User $user, string $bookingStatus = 'pending', string $contractStatus = 'sent'): Bookings
    {
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'status'          => $bookingStatus,
            'contract_option' => 'default',
        ]);
        $booking->contract()->create([
            'author_id'   => $user->id,
            'status'      => $contractStatus,
            'envelope_id' => 'pd-doc-456',
        ]);

        return $booking;
    }

    public function test_amend_returns_booking_back_in_draft(): void
    {
        Http::fake(['api.pandadoc.com/*' => Http::response([], 200)]);

        $user    = User::factory()->create();
        $booking = $this->makeBooking($user);
        $token   = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $booking->band_id])
            ->postJson("/api/mobile/bands/{$booking->band_id}/bookings/{$booking->id}/contract/amend");

        $response->assertOk()
            ->assertJsonPath('booking.status', 'draft')
            ->assertJsonPath('booking.contract.status', 'pending')
            ->assertJsonPath('booking.contract.envelope_id', null);
    }

    public function test_amend_rejects_unsent_contract_with_422(): void
    {
        Http::fake();

        $user    = User::factory()->create();
        $booking = $this->makeBooking($user, bookingStatus: 'draft', contractStatus: 'pending');
        $token   = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $booking->band_id])
            ->postJson("/api/mobile/bands/{$booking->band_id}/bookings/{$booking->id}/contract/amend")
            ->assertStatus(422);

        Http::assertNothingSent();
    }
}
