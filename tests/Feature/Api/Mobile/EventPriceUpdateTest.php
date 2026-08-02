<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `PATCH /api/mobile/events/{event}` for the price field. Same
 * regression class as venue_name/venue_address: the mobile app sends
 * `price` but the validator didn't declare it and the controller never
 * wrote it, so per-event price edits were silently discarded.
 */
class EventPriceUpdateTest extends TestCase
{
    use RefreshDatabase;

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
            'price'          => '1000',
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

    public function test_update_persists_price_on_the_event(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'price' => '1500',
        ])->assertOk();

        // Price cast stores cents; accessor formats back to a dollars string.
        $this->assertSame('1500.00', $event->fresh()->price);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'price' => 150000]);
    }

    public function test_update_rejects_non_numeric_price(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'price' => 'a lot',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_update_without_price_leaves_it_unchanged(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'title' => 'Renamed',
        ])->assertOk();

        $this->assertSame('1000.00', $event->fresh()->price);
    }
}
