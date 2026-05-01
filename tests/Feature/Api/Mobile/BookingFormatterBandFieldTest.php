<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFormatterBandFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_index_response_includes_band_field(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create([
            'name'        => 'Test Band',
            'is_personal' => false,
        ]);
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'confirmed',
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings");

        $response->assertOk();

        $bookings = collect($response->json('bookings'));
        $first = $bookings->firstWhere('id', $booking->id);
        $this->assertNotNull($first);
        $this->assertArrayHasKey('band', $first);
        $this->assertSame($band->id, $first['band']['id']);
        $this->assertSame('Test Band', $first['band']['name']);
        $this->assertFalse($first['band']['is_personal']);
        $this->assertArrayHasKey('logo_url', $first['band']);
    }

    public function test_booking_show_response_includes_band_field(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create([
            'name'        => 'Personal',
            'is_personal' => true,
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

        $response->assertOk();

        $bookingJson = $response->json('booking');
        $this->assertArrayHasKey('band', $bookingJson);
        $this->assertSame($band->id, $bookingJson['band']['id']);
        $this->assertTrue($bookingJson['band']['is_personal']);
        $this->assertArrayHasKey('logo_url', $bookingJson['band']);
    }
}
