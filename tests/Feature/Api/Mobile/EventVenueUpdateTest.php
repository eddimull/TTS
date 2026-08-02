<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `PATCH /api/mobile/events/{event}` for the venue_name /
 * venue_address fields. Regression coverage for the controller writing
 * venue fields to `$event->eventable` (the bookings row) after the
 * 2026-05-03 migration moved those columns to events — Eloquent silently
 * discarded the non-fillable attributes and the endpoint reported success
 * while persisting nothing.
 */
class EventVenueUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a user that owns a band, a booking owned by that band, and a
     * polymorphic Events row attached to the booking.
     */
    private function makeOwnedEvent(array $eventOverrides = []): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        $event = Events::factory()->create(array_merge([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'date'           => now()->addDays(7)->format('Y-m-d'),
            'venue_name'     => 'TBD',
            'venue_address'  => null,
        ], $eventOverrides));

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'booking', 'event', 'token');
    }

    private function patchEvent(string $token, Bands $band, Events $event, array $payload)
    {
        return $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson("/api/mobile/events/{$event->key}", $payload);
    }

    public function test_update_persists_venue_fields_on_the_event(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'venue_name'    => 'The Fillmore',
            'venue_address' => '1805 Geary Blvd, San Francisco, CA',
        ])->assertOk();

        $fresh = $event->fresh();
        $this->assertSame('The Fillmore', $fresh->venue_name);
        $this->assertSame('1805 Geary Blvd, San Francisco, CA', $fresh->venue_address);
    }

    public function test_update_persists_venue_when_contract_is_signed(): void
    {
        ['band' => $band, 'booking' => $booking, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        Contracts::factory()->create([
            'contractable_id'   => $booking->id,
            'contractable_type' => Bookings::class,
            'status'            => 'completed',
        ]);

        $this->patchEvent($token, $band, $event, [
            'venue_name'    => 'City Park Pavilion',
            'venue_address' => '1 Palm Dr, New Orleans, LA',
        ])->assertOk();

        $fresh = $event->fresh();
        $this->assertSame('City Park Pavilion', $fresh->venue_name);
        $this->assertSame('1 Palm Dr, New Orleans, LA', $fresh->venue_address);
    }

    public function test_update_can_clear_venue_with_null(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent([
            'venue_name'    => 'Old Venue',
            'venue_address' => '123 Old St',
        ]);

        $this->patchEvent($token, $band, $event, [
            'venue_name'    => null,
            'venue_address' => null,
        ])->assertOk();

        $fresh = $event->fresh();
        $this->assertNull($fresh->venue_name);
        $this->assertNull($fresh->venue_address);
    }

    public function test_update_without_venue_fields_leaves_them_unchanged(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent([
            'venue_name'    => 'Keep Me',
            'venue_address' => '456 Keep Ave',
        ]);

        $this->patchEvent($token, $band, $event, [
            'title' => 'Renamed',
        ])->assertOk();

        $fresh = $event->fresh();
        $this->assertSame('Renamed', $fresh->title);
        $this->assertSame('Keep Me', $fresh->venue_name);
        $this->assertSame('456 Keep Ave', $fresh->venue_address);
    }
}
