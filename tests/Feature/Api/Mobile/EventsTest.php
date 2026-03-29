<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\LiveSetlistSession;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUserWithBandAndEvent(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $eventType = EventTypes::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'booking', 'event', 'token');
    }

    // -------------------------------------------------------------------------
    // events.index
    // -------------------------------------------------------------------------

    public function test_events_index_requires_band_header(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        // No X-Band-ID header should return 422
        $this->withToken($token)
            ->getJson('/api/mobile/bands/1/events')
            ->assertStatus(422);
    }

    public function test_events_index_returns_403_for_non_member(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        // Band that the user does not belong to
        $otherBand = Bands::factory()->create();

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $otherBand->id])
            ->getJson("/api/mobile/bands/{$otherBand->id}/events")
            ->assertStatus(403);
    }

    public function test_events_index_returns_band_events(): void
    {
        [
            'band'  => $band,
            'event' => $event,
            'token' => $token,
        ] = $this->createUserWithBandAndEvent();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/events");

        $response->assertOk()
            ->assertJsonStructure([
                'events' => [
                    '*' => ['id', 'key', 'title', 'date', 'time', 'event_source', 'live_session_id'],
                ],
            ]);

        $ids = collect($response->json('events'))->pluck('id');
        $this->assertTrue($ids->contains($event->id));
    }

    public function test_events_index_filters_by_date_range(): void
    {
        ['band' => $band, 'token' => $token] = $this->createUserWithBandAndEvent();

        $eventType = EventTypes::factory()->create();
        $booking2 = Bookings::factory()->create(['band_id' => $band->id]);
        $pastEvent = Events::factory()->create([
            'eventable_id'   => $booking2->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => now()->subDays(30)->format('Y-m-d'),
        ]);

        $from = now()->format('Y-m-d');

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/events?from={$from}");

        $response->assertOk();

        $ids = collect($response->json('events'))->pluck('id');
        $this->assertFalse($ids->contains($pastEvent->id));
    }

    // -------------------------------------------------------------------------
    // events.show
    // -------------------------------------------------------------------------

    public function test_event_show_returns_event_detail(): void
    {
        [
            'event' => $event,
            'token' => $token,
        ] = $this->createUserWithBandAndEvent();

        $response = $this->withToken($token)
            ->getJson("/api/mobile/events/{$event->key}");

        $response->assertOk()
            ->assertJsonStructure([
                'event' => [
                    'id',
                    'key',
                    'title',
                    'date',
                    'time',
                    'notes',
                    'event_type',
                    'event_type_id',
                    'venue_name',
                    'venue_address',
                    'status',
                    'eventable_type',
                    'eventable_id',
                    'can_write',
                    'live_session_id',
                    'members',
                ],
            ]);

        $this->assertEquals($event->id, $response->json('event.id'));
        $this->assertEquals($event->key, $response->json('event.key'));
    }

    public function test_event_show_requires_authentication(): void
    {
        ['event' => $event] = $this->createUserWithBandAndEvent();

        $this->getJson("/api/mobile/events/{$event->key}")->assertUnauthorized();
    }

    public function test_event_show_returns_403_for_user_without_access(): void
    {
        ['event' => $event] = $this->createUserWithBandAndEvent();

        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('test-device')->plainTextToken;

        $this->withToken($otherToken)
            ->getJson("/api/mobile/events/{$event->key}")
            ->assertStatus(403);
    }

    public function test_event_show_returns_404_for_unknown_key(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/mobile/events/no-such-key-here')
            ->assertNotFound();
    }

    public function test_event_show_can_write_is_false_for_read_only_member(): void
    {
        ['event' => $event] = $this->createUserWithBandAndEvent();

        // Create a read-only member (no write permission)
        $reader = User::factory()->create();
        $band = Bands::find(Events::find($event->id)->eventable->band_id);
        BandMembers::factory()->create(['user_id' => $reader->id, 'band_id' => $band->id]);
        $reader->assignBandMemberDefaults($band->id);

        $token = $reader->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/mobile/events/{$event->key}");

        $response->assertOk();
        $this->assertFalse($response->json('event.can_write'));
    }

    public function test_event_show_date_is_formatted_as_iso_string(): void
    {
        ['event' => $event, 'token' => $token] = $this->createUserWithBandAndEvent();

        $response = $this->withToken($token)
            ->getJson("/api/mobile/events/{$event->key}");

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $response->json('event.date'),
            'date must be Y-m-d format'
        );
    }

    public function test_event_show_members_is_empty_for_event_without_roster(): void
    {
        ['event' => $event, 'token' => $token] = $this->createUserWithBandAndEvent();

        $response = $this->withToken($token)
            ->getJson("/api/mobile/events/{$event->key}");

        $response->assertOk();
        $this->assertIsArray($response->json('event.members'));
        $this->assertEmpty($response->json('event.members'));
    }

    public function test_event_show_live_session_id_is_null_when_no_active_session(): void
    {
        ['event' => $event, 'token' => $token] = $this->createUserWithBandAndEvent();

        $response = $this->withToken($token)
            ->getJson("/api/mobile/events/{$event->key}");

        $response->assertOk();
        $this->assertNull($response->json('event.live_session_id'));
    }

    public function test_events_index_event_source_is_booking_for_booking_events(): void
    {
        [
            'band'  => $band,
            'event' => $event,
            'token' => $token,
        ] = $this->createUserWithBandAndEvent();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/events");

        $response->assertOk();

        $eventData = collect($response->json('events'))->firstWhere('id', $event->id);
        $this->assertNotNull($eventData);
        $this->assertEquals('booking', $eventData['event_source']);
    }
}
