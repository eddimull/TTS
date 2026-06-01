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

    // -------------------------------------------------------------------------
    // setlist editor update
    // -------------------------------------------------------------------------

    public function test_update_creates_setlist_and_saves_songs(): void
    {
        ['band' => $band, 'event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        $song = Song::factory()->forBand($band)->active()->create(['lead_singer_id' => null]);

        $resp = $this->withHeaders($headers)->putJson("/api/mobile/events/{$event->key}/setlist", [
            'songs' => [
                ['type' => 'song', 'song_id' => $song->id, 'notes' => 'Open with this'],
                ['type' => 'break'],
                ['type' => 'song', 'custom_title' => 'Custom Tune', 'custom_artist' => 'Indie Band'],
            ],
            'status' => 'draft',
        ]);

        $resp->assertOk()
            ->assertJsonCount(3, 'songs')
            ->assertJsonPath('songs.0.song_id', $song->id)
            ->assertJsonPath('songs.0.position', 1)
            ->assertJsonPath('songs.0.notes', 'Open with this')
            ->assertJsonPath('songs.1.type', 'break')
            ->assertJsonPath('songs.1.position', 2)
            ->assertJsonPath('songs.2.custom_title', 'Custom Tune')
            ->assertJsonPath('songs.2.position', 3);

        $this->assertDatabaseHas('event_setlists', ['event_id' => $event->id, 'status' => 'draft']);
        $this->assertDatabaseCount('setlist_songs', 3);
    }

    public function test_update_rejects_song_from_another_band(): void
    {
        ['event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        $otherBand = Bands::factory()->create();
        $foreignSong = Song::factory()->forBand($otherBand)->active()->create(['lead_singer_id' => null]);

        $this->withHeaders($headers)->putJson("/api/mobile/events/{$event->key}/setlist", [
            'songs' => [['type' => 'song', 'song_id' => $foreignSong->id]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('setlist_songs', 0);
    }

    public function test_update_without_status_preserves_existing(): void
    {
        ['band' => $band, 'event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        EventSetlist::create(['event_id' => $event->id, 'band_id' => $band->id, 'status' => 'ready']);

        $this->withHeaders($headers)->putJson("/api/mobile/events/{$event->key}/setlist", [
            'songs' => [['type' => 'song', 'custom_title' => 'Tune']],
        ])->assertOk();

        $this->assertDatabaseHas('event_setlists', ['event_id' => $event->id, 'status' => 'ready']);
    }

    public function test_update_replaces_existing_songs(): void
    {
        ['band' => $band, 'event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        $setlist = EventSetlist::create(['event_id' => $event->id, 'band_id' => $band->id, 'status' => 'draft']);
        SetlistSong::create(['setlist_id' => $setlist->id, 'type' => 'song', 'custom_title' => 'Old', 'position' => 1]);

        $this->withHeaders($headers)->putJson("/api/mobile/events/{$event->key}/setlist", [
            'songs' => [['type' => 'song', 'custom_title' => 'New']],
        ])->assertOk();

        $this->assertDatabaseCount('setlist_songs', 1);
        $this->assertDatabaseHas('setlist_songs', ['custom_title' => 'New']);
        $this->assertDatabaseMissing('setlist_songs', ['custom_title' => 'Old']);
    }

    public function test_update_marks_ready(): void
    {
        ['event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        $this->withHeaders($headers)->putJson("/api/mobile/events/{$event->key}/setlist", [
            'songs'  => [],
            'status' => 'ready',
        ])->assertOk();

        $this->assertDatabaseHas('event_setlists', ['event_id' => $event->id, 'status' => 'ready']);
    }

    public function test_update_rejects_non_writer(): void
    {
        ['band' => $band, 'event' => $event] = $this->makeEventForOwner();

        $nonMember = User::factory()->create();
        $token = $nonMember->createToken('test-device')->plainTextToken;

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $band->id,
            'Accept'        => 'application/json',
        ])->putJson("/api/mobile/events/{$event->key}/setlist", ['songs' => []])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // setlist editor generate (AI)
    // -------------------------------------------------------------------------

    public function test_generate_creates_setlist_via_ai_service(): void
    {
        ['band' => $band, 'event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        // Explicit titles so the band's songs() query (orderBy title) is
        // deterministic — the fake AI returns them in query order, so the
        // ordering assertions below depend on a stable sort.
        $song1 = Song::factory()->forBand($band)->active()->create(['lead_singer_id' => null, 'title' => 'AAA First']);
        $song2 = Song::factory()->forBand($band)->active()->create(['lead_singer_id' => null, 'title' => 'BBB Second']);

        config(['services.anthropic.key' => 'test-key']);

        // Fake SetlistAiService returning a deterministic ordering (the two song ids).
        $this->app->bind(\App\Services\SetlistAiService::class, function () {
            return new class extends \App\Services\SetlistAiService {
                public function __construct() {}
                public function buildImageBlocks($attachments): array { return []; }
                public function generateSetlist(\App\Models\Events $event, array $songs, ?string $extraContext = null, array $attachmentImages = [], ?callable $progress = null): array
                {
                    return [
                        'items'         => array_map(fn ($s) => $s['id'], $songs),
                        'event_context' => 'Test event context',
                        'image_context' => [],
                    ];
                }
            };
        });

        $resp = $this->withHeaders($headers)->postJson("/api/mobile/events/{$event->key}/setlist/generate", [
            'context' => 'Keep it upbeat',
        ]);

        $resp->assertOk()
            ->assertJsonCount(2, 'songs')
            ->assertJsonPath('event_context', 'Test event context')
            ->assertJsonPath('songs.0.song_id', $song1->id)
            ->assertJsonPath('songs.0.position', 1)
            ->assertJsonPath('songs.1.song_id', $song2->id)
            ->assertJsonPath('songs.1.position', 2);

        $this->assertDatabaseHas('event_setlists', ['event_id' => $event->id]);
        $this->assertDatabaseCount('setlist_songs', 2);
    }

    public function test_generate_rejects_non_writer(): void
    {
        ['band' => $band, 'event' => $event] = $this->makeEventForOwner();

        Song::factory()->forBand($band)->active()->create(['lead_singer_id' => null]);

        $nonMember = User::factory()->create();
        $token = $nonMember->createToken('test-device')->plainTextToken;

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $band->id,
            'Accept'        => 'application/json',
        ])->postJson("/api/mobile/events/{$event->key}/setlist/generate")
            ->assertForbidden();
    }

    public function test_generate_fails_without_api_key(): void
    {
        ['band' => $band, 'event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        Song::factory()->forBand($band)->active()->create(['lead_singer_id' => null]);

        config(['services.anthropic.key' => null]);

        $this->withHeaders($headers)->postJson("/api/mobile/events/{$event->key}/setlist/generate")
            ->assertStatus(503);
    }

    public function test_generate_fails_with_empty_library(): void
    {
        ['event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        config(['services.anthropic.key' => 'test-key']);

        $this->withHeaders($headers)->postJson("/api/mobile/events/{$event->key}/setlist/generate")
            ->assertStatus(422);
    }

    public function test_refine_replaces_setlist_using_ai_service(): void
    {
        ['band' => $band, 'event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        $song1 = Song::factory()->forBand($band)->active()->create(['lead_singer_id' => null]);
        $song2 = Song::factory()->forBand($band)->active()->create(['lead_singer_id' => null]);

        // Existing setlist with song1
        $setlist = EventSetlist::create(['event_id' => $event->id, 'band_id' => $band->id, 'status' => 'draft']);
        SetlistSong::create(['setlist_id' => $setlist->id, 'type' => 'song', 'song_id' => $song1->id, 'position' => 1]);

        // Fake AI returns song2 only + a summary
        $this->app->bind(\App\Services\SetlistAiService::class, function () use ($song2) {
            return new class($song2->id) extends \App\Services\SetlistAiService {
                public function __construct(private int $s2Id) {}
                public function refineSetlist(\App\Models\Events $event, array $songs, array $currentSetlist, array $chatHistory, string $newMessage): array
                {
                    return [
                        'setlist' => [$this->s2Id],
                        'summary' => 'Swapped first song.',
                    ];
                }
            };
        });

        $resp = $this->withHeaders($headers)->postJson("/api/mobile/events/{$event->key}/setlist/refine", [
            'message' => 'Swap the first one',
        ]);

        $resp->assertOk()
            ->assertJsonPath('summary', 'Swapped first song.')
            ->assertJsonPath('setlist.songs.0.song_id', $song2->id)
            ->assertJsonCount(1, 'setlist.songs');
    }

    public function test_refine_requires_existing_setlist(): void
    {
        ['event' => $event, 'headers' => $headers] = $this->makeEventForOwner();

        $this->withHeaders($headers)->postJson("/api/mobile/events/{$event->key}/setlist/refine", [
            'message' => 'Change something',
        ])->assertStatus(422);
    }

    public function test_refine_rejects_non_writer(): void
    {
        ['band' => $band, 'event' => $event] = $this->makeEventForOwner();

        $nonMember = User::factory()->create();
        $token = $nonMember->createToken('test-device')->plainTextToken;

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $band->id,
            'Accept'        => 'application/json',
        ])->postJson("/api/mobile/events/{$event->key}/setlist/refine", [
            'message' => 'Change something',
        ])->assertForbidden();
    }
}
