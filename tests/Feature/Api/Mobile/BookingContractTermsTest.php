<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingContractTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_owner_can_save_contract_terms(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'draft',
        ]);

        $booking->contract()->create([
            'author_id'    => $user->id,
            'custom_terms' => [['title' => 'Old', 'content' => 'Old content']],
            'status'       => 'pending',
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $payload = [
            'custom_terms' => [
                ['title' => 'Cancellation', 'content' => 'No refunds within 7 days.'],
                ['title' => 'Payment',      'content' => '50% deposit required.'],
            ],
        ];

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/terms", $payload);

        $response->assertOk()
            ->assertJsonPath('booking.contract.custom_terms.0.title',   'Cancellation')
            ->assertJsonPath('booking.contract.custom_terms.0.content', 'No refunds within 7 days.')
            ->assertJsonPath('booking.contract.custom_terms.1.title',   'Payment')
            ->assertJsonPath('booking.contract.custom_terms.1.content', '50% deposit required.');
    }

    public function test_buyer_name_override_is_persisted_and_returned(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'draft',
        ]);

        $booking->contract()->create([
            'author_id'    => $user->id,
            'custom_terms' => [['title' => 'Old', 'content' => 'Old content']],
            'status'       => 'pending',
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $payload = [
            'custom_terms' => [
                ['title' => 'Cancellation', 'content' => 'No refunds within 7 days.'],
            ],
            'buyer_name_override' => 'The City of Scott',
        ];

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/terms", $payload);

        $response->assertOk();

        $this->assertSame('The City of Scott', $response->json('booking.contract.buyer_name_override'));

        $this->assertDatabaseHas('contracts', [
            'contractable_id'     => $booking->id,
            'contractable_type'   => Bookings::class,
            'buyer_name_override' => 'The City of Scott',
        ]);
    }

    public function test_missing_custom_terms_returns_422(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'draft',
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/terms", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_terms']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'draft',
        ]);

        $this->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/terms", [
            'custom_terms' => [['title' => 'X', 'content' => 'Y']],
        ])->assertUnauthorized();
    }
}
