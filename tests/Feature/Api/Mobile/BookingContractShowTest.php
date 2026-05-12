<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingContractShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_contract_includes_custom_terms_and_updated_at(): void
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
            'custom_terms' => [['title' => 'A', 'content' => 'B']],
            'status'       => 'pending',
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract");

        $response->assertOk()
            ->assertJsonPath('contract.custom_terms.0.title', 'A')
            ->assertJsonPath('contract.custom_terms.0.content', 'B')
            ->assertJsonStructure([
                'contract' => ['id', 'status', 'asset_url', 'envelope_id', 'custom_terms', 'updated_at'],
            ]);
    }

    public function test_booking_detail_includes_band_address_fields(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create([
            'address' => '123 Music Row',
            'city'    => 'Nashville',
            'state'   => 'TN',
            'zip'     => '37203',
            'logo'    => 'https://example.com/logo.png',
        ]);
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'confirmed',
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}");

        $response->assertOk()
            ->assertJsonPath('booking.band.address', '123 Music Row')
            ->assertJsonPath('booking.band.city',    'Nashville')
            ->assertJsonPath('booking.band.state',   'TN')
            ->assertJsonPath('booking.band.zip',     '37203')
            ->assertJsonPath('booking.band.logo',    'https://example.com/logo.png');
    }
}
