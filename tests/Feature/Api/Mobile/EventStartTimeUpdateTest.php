<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `PATCH /api/mobile/events/{event}` for the start_time / end_time
 * fields. Regression coverage for the case where the mobile app sent
 * `start_time` (and `end_time`) but the validator only declared the
 * legacy `time` field (dropped by the 2026-05-03 migration), so the
 * controller silently discarded the new values.
 */
class EventStartTimeUpdateTest extends TestCase
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
            'start_time'     => '19:00',
            'end_time'       => '22:00',
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

    public function test_update_persists_start_time(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'start_time' => '20:30',
        ])->assertOk();

        // start_time is cast to a datetime; format back to H:i for comparison.
        $this->assertSame('20:30', $event->fresh()->start_time->format('H:i'));
        // end_time untouched.
        $this->assertSame('22:00', $event->fresh()->end_time->format('H:i'));
    }

    public function test_update_persists_end_time(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'end_time' => '23:45',
        ])->assertOk();

        $this->assertSame('23:45', $event->fresh()->end_time->format('H:i'));
        $this->assertSame('19:00', $event->fresh()->start_time->format('H:i'));
    }

    public function test_update_persists_both_times_together(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'start_time' => '18:00',
            'end_time'   => '21:30',
        ])->assertOk();

        $fresh = $event->fresh();
        $this->assertSame('18:00', $fresh->start_time->format('H:i'));
        $this->assertSame('21:30', $fresh->end_time->format('H:i'));
    }

    public function test_update_can_clear_start_time_with_null(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'start_time' => null,
        ])->assertOk();

        $this->assertNull($event->fresh()->start_time);
    }

    public function test_update_rejects_invalid_start_time_format(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'start_time' => '7:00 PM', // not H:i
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_time');
    }

    public function test_update_without_start_time_leaves_it_unchanged(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'title' => 'Renamed',
        ])->assertOk();

        $fresh = $event->fresh();
        $this->assertSame('Renamed', $fresh->title);
        $this->assertSame('19:00', $fresh->start_time->format('H:i'));
    }
}
