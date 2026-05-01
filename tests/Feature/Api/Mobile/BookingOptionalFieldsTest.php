<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingOptionalFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserAndBand(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'token');
    }

    public function test_create_booking_without_price_succeeds_and_defaults_to_zero(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeUserAndBand();
        $eventType = EventTypes::firstOrCreate(['name' => 'Wedding']);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson(
                "/api/mobile/bands/{$band->id}/bookings",
                [
                    'name'          => 'Cheap Gig',
                    'event_type_id' => $eventType->id,
                    'date'          => '2026-06-01',
                    'start_time'    => '19:00',
                    'duration'      => 3,
                    // No 'price' key — testing that the validator no longer rejects this.
                ],
            );

        $response->assertCreated();

        $bookingId = $response->json('booking.id');
        $this->assertNotNull($bookingId);

        $stored = Bookings::find($bookingId);
        $this->assertSame(0, (int) $stored->getRawOriginal('price'),
            'Schema default of 0 should kick in when price is omitted');
    }

    public function test_create_booking_with_explicit_null_price_succeeds(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeUserAndBand();
        $eventType = EventTypes::firstOrCreate(['name' => 'Wedding']);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson(
                "/api/mobile/bands/{$band->id}/bookings",
                [
                    'name'          => 'Free Gig',
                    'event_type_id' => $eventType->id,
                    'date'          => '2026-06-02',
                    'start_time'    => '19:00',
                    'duration'      => 3,
                    'price'         => null,
                ],
            );

        $response->assertCreated();
        $stored = Bookings::find($response->json('booking.id'));
        $this->assertSame(0, (int) $stored->getRawOriginal('price'));
    }

    public function test_update_booking_without_price_succeeds(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeUserAndBand();

        $booking = Bookings::factory()->create([
            'name'    => 'Existing',
            'date'    => '2026-06-01',
            'band_id' => $band->id,
        ]);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson(
                "/api/mobile/bands/{$band->id}/bookings/{$booking->id}",
                ['name' => 'Renamed', 'price' => null],
            );

        $response->assertOk();
    }

    public function test_create_booking_with_negative_price_still_rejected(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeUserAndBand();
        $eventType = EventTypes::firstOrCreate(['name' => 'Wedding']);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson(
                "/api/mobile/bands/{$band->id}/bookings",
                [
                    'name'          => 'Bad',
                    'event_type_id' => $eventType->id,
                    'date'          => '2026-06-01',
                    'start_time'    => '19:00',
                    'duration'      => 3,
                    'price'         => -10,
                ],
            );

        $response->assertStatus(422);
    }

    public function test_create_booking_without_venue_name_succeeds_and_defaults_to_tbd(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeUserAndBand();
        $eventType = EventTypes::firstOrCreate(['name' => 'Wedding']);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson(
                "/api/mobile/bands/{$band->id}/bookings",
                [
                    'name'          => 'No Venue',
                    'event_type_id' => $eventType->id,
                    'date'          => '2026-06-01',
                    'start_time'    => '19:00',
                    'duration'      => 3,
                    // No venue_name — schema default of 'TBD' should kick in.
                ],
            );

        $response->assertCreated();
        $stored = Bookings::find($response->json('booking.id'));
        $this->assertSame('TBD', $stored->venue_name);
    }

    public function test_create_booking_with_explicit_null_venue_name_succeeds(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeUserAndBand();
        $eventType = EventTypes::firstOrCreate(['name' => 'Wedding']);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson(
                "/api/mobile/bands/{$band->id}/bookings",
                [
                    'name'          => 'Null Venue',
                    'event_type_id' => $eventType->id,
                    'date'          => '2026-06-02',
                    'start_time'    => '19:00',
                    'duration'      => 3,
                    'venue_name'    => null,
                ],
            );

        $response->assertCreated();
        $stored = Bookings::find($response->json('booking.id'));
        $this->assertSame('TBD', $stored->venue_name,
            'Null venue_name must not crash on the NOT NULL column; controller should let schema default fire');
    }

    public function test_create_booking_with_empty_string_venue_name_succeeds(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeUserAndBand();
        $eventType = EventTypes::firstOrCreate(['name' => 'Wedding']);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson(
                "/api/mobile/bands/{$band->id}/bookings",
                [
                    'name'          => 'Empty Venue',
                    'event_type_id' => $eventType->id,
                    'date'          => '2026-06-03',
                    'start_time'    => '19:00',
                    'duration'      => 3,
                    'venue_name'    => '',
                ],
            );

        $response->assertCreated();
        $stored = Bookings::find($response->json('booking.id'));
        $this->assertSame('TBD', $stored->venue_name);
    }

    public function test_update_booking_with_null_venue_name_does_not_overwrite_existing(): void
    {
        ['band' => $band, 'token' => $token] = $this->makeUserAndBand();

        $booking = Bookings::factory()->create([
            'name'       => 'Has Venue',
            'date'       => '2026-06-01',
            'band_id'    => $band->id,
            'venue_name' => 'The Real Venue',
        ]);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson(
                "/api/mobile/bands/{$band->id}/bookings/{$booking->id}",
                ['name' => 'Renamed', 'venue_name' => null],
            );

        $response->assertOk();
        $this->assertSame('The Real Venue', $booking->fresh()->venue_name,
            'Null venue_name on update must not overwrite — drop the key instead');
    }
}
