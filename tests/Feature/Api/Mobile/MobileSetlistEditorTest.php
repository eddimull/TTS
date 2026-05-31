<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventSetlist;
use App\Models\EventTypes;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSetlistEditorTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeEventForOwner(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $eventType = EventTypes::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);

        $token = $user->createToken('test-device')->plainTextToken;
        $headers = [
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $band->id,
            'Accept'        => 'application/json',
        ];

        return compact('user', 'band', 'event', 'token', 'headers');
    }

    // -------------------------------------------------------------------------
    // setlist editor show
    // -------------------------------------------------------------------------

    public function test_show_returns_empty_setlist_when_none_exists(): void
    {
        ['event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        $resp = $this->withHeaders($headers)
            ->getJson("/api/mobile/events/{$event->key}/setlist");

        $resp->assertOk()
            ->assertJson(['setlist' => null, 'can_write' => true])
            ->assertJsonStructure(['event', 'setlist', 'songs', 'can_write'])
            ->assertJsonCount(0, 'songs');
    }

    public function test_show_returns_setlist_with_songs(): void
    {
        ['band' => $band, 'event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        $song = Song::factory()->forBand($band)->active()->create(['lead_singer_id' => null]);

        $setlist = EventSetlist::create([
            'event_id' => $event->id,
            'band_id'  => $band->id,
            'status'   => 'draft',
        ]);

        SetlistSong::create([
            'setlist_id' => $setlist->id,
            'type'       => 'song',
            'song_id'    => $song->id,
            'position'   => 1,
        ]);

        $resp = $this->withHeaders($headers)
            ->getJson("/api/mobile/events/{$event->key}/setlist");

        $resp->assertOk()
            ->assertJsonPath('setlist.status', 'draft')
            ->assertJsonCount(1, 'setlist.songs')
            ->assertJsonPath('setlist.songs.0.song_id', $song->id)
            ->assertJsonCount(1, 'songs')
            ->assertJsonPath('songs.0.id', $song->id);
    }

    public function test_show_forbidden_for_non_member(): void
    {
        ['band' => $band, 'event' => $event] = $this->makeEventForOwner();

        $nonMember = User::factory()->create();
        $token = $nonMember->createToken('test-device')->plainTextToken;

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $band->id,
            'Accept'        => 'application/json',
        ])
            ->getJson("/api/mobile/events/{$event->key}/setlist")
            ->assertForbidden();
    }
}
