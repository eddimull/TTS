<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `end_time` in the mobile event payloads. The column has always
 * existed (and the Flutter models parse `end_time`), but neither
 * formatForShow() nor formatForList() ever emitted it — the app only
 * appeared to show an end time via the seeded "End Time" timeline pin,
 * which the web-parity change stopped seeding. Without this field the
 * event edit screen's End Time always reads "Not set" and the detail
 * glance card never shows "ends …".
 */
class EventEndTimeSerializationTest extends TestCase
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
        ], $eventOverrides));

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'booking', 'event', 'token');
    }

    public function test_show_includes_end_time_as_hhmm(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] =
            $this->makeOwnedEvent(['start_time' => '19:00', 'end_time' => '23:00']);

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/events/{$event->key}")
            ->assertOk()
            ->assertJsonPath('event.end_time', '23:00');
    }

    public function test_show_end_time_null_when_unset(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] =
            $this->makeOwnedEvent(['start_time' => '19:00', 'end_time' => null]);

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/events/{$event->key}")
            ->assertOk()
            // assertJsonPath(…, null) alone matches an ABSENT key too —
            // require the key to actually be present with a null value.
            ->assertJsonStructure(['event' => ['end_time']])
            ->assertJsonPath('event.end_time', null);
    }

    public function test_show_serializes_midnight_end_time(): void
    {
        // A 4-hour default duration from 8 PM lands exactly on midnight;
        // "00:00" must survive serialization (not degrade to null/absent).
        ['band' => $band, 'event' => $event, 'token' => $token] =
            $this->makeOwnedEvent(['start_time' => '20:00', 'end_time' => '00:00']);

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/events/{$event->key}")
            ->assertOk()
            ->assertJsonPath('event.end_time', '00:00');
    }

    public function test_index_includes_end_time_as_hhmm(): void
    {
        ['band' => $band, 'token' => $token] =
            $this->makeOwnedEvent(['start_time' => '19:00', 'end_time' => '23:30']);

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/events")
            ->assertOk()
            ->assertJsonPath('events.0.end_time', '23:30');
    }
}
