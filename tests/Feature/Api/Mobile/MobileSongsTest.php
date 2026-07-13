<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\Bands;
use App\Models\Charts;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSongsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{user: User, band: Bands, headers: array<string, mixed>} */
    private function makeOwner(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-device', ['mobile', 'read:songs', 'write:songs'])->plainTextToken;

        return [
            'user' => $user,
            'band' => $band,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'X-Band-ID' => $band->id,
                'Accept' => 'application/json',
            ],
        ];
    }

    /** Member holding only the given per-band permissions, token minted with matching abilities. */
    private function makeMember(Bands $band, array $permissions, array $abilities): array
    {
        $user = User::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);
        setPermissionsTeamId($band->id);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }
        setPermissionsTeamId(0);
        $token = $user->createToken('test-device', array_merge(['mobile'], $abilities))->plainTextToken;

        return [
            'user' => $user,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'X-Band-ID' => $band->id,
                'Accept' => 'application/json',
            ],
        ];
    }

    public function test_index_returns_expanded_song_payload(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->active()->create([
            'title' => 'Uptown Funk', 'rating' => 8, 'energy' => 9, 'notes' => 'Horns!',
            'lead_singer_id' => null, 'transition_song_id' => null,
        ]);
        Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id, 'title' => 'Uptown Funk - Horns']);

        $resp = $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs");

        $resp->assertOk()
            ->assertJsonPath('songs.0.title', 'Uptown Funk')
            ->assertJsonPath('songs.0.rating', 8)
            ->assertJsonPath('songs.0.energy', 9)
            ->assertJsonPath('songs.0.notes', 'Horns!')
            ->assertJsonPath('songs.0.active', true)
            ->assertJsonPath('songs.0.charts.0.title', 'Uptown Funk - Horns')
            ->assertJsonStructure(['songs', 'genres']);
    }

    public function test_index_excludes_inactive_by_default_and_includes_with_flag(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        Song::factory()->forBand($band)->active()->create(['title' => 'Active One']);
        Song::factory()->forBand($band)->inactive()->create(['title' => 'Retired One']);

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs")
            ->assertOk()->assertJsonCount(1, 'songs');

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs?include_inactive=1")
            ->assertOk()->assertJsonCount(2, 'songs');
    }

    public function test_index_requires_read_songs(): void
    {
        ['band' => $band] = $this->makeOwner();
        // Member with charts perms only — old-style token without read:songs.
        ['headers' => $headers] = $this->makeMember($band, ['read:charts'], ['read:charts']);

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs")
            ->assertForbidden();
    }

    public function test_index_allows_member_with_read_songs(): void
    {
        ['band' => $band] = $this->makeOwner();
        ['headers' => $headers] = $this->makeMember($band, ['read:songs'], ['read:songs']);

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs")
            ->assertOk();
    }

    public function test_member_with_write_songs_can_create_a_song(): void
    {
        ['band' => $band] = $this->makeOwner();
        ['headers' => $headers] = $this->makeMember($band, ['read:songs', 'write:songs'], ['read:songs', 'write:songs']);

        $resp = $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/songs", [
            'title' => 'September',
            'artist' => 'Earth, Wind & Fire',
            'bpm' => 126,
            'energy' => 10,
            'active' => true,
        ]);

        $resp->assertCreated()
            ->assertJsonPath('song.title', 'September')
            ->assertJsonPath('song.energy', 10);
        $this->assertDatabaseHas('songs', ['band_id' => $band->id, 'title' => 'September']);
    }

    public function test_member_without_write_songs_cannot_create(): void
    {
        ['band' => $band] = $this->makeOwner();
        ['headers' => $headers] = $this->makeMember($band, ['read:songs'], ['read:songs']);

        $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/songs", [
            'title' => 'Nope',
        ])->assertForbidden();
    }

    public function test_create_requires_title(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();

        $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/songs", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_edits_a_song(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->active()->create(['title' => 'Old']);

        $resp = $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/songs/{$song->id}", [
            'title' => 'New Title',
            'active' => false,
        ]);

        $resp->assertOk()
            ->assertJsonPath('song.title', 'New Title')
            ->assertJsonPath('song.active', false);
    }

    public function test_update_rejects_song_from_another_band(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $otherBand = Bands::factory()->create();
        $foreign = Song::factory()->forBand($otherBand)->create();

        $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/songs/{$foreign->id}", [
            'title' => 'Hijack',
        ])->assertNotFound();
    }

    public function test_index_returns_populated_lead_singer_and_transition_song_shapes(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $roster = \App\Models\Roster::factory()->create(['band_id' => $band->id]);
        $member = \App\Models\RosterMember::factory()->create(['roster_id' => $roster->id]);
        $target = Song::factory()->forBand($band)->active()->create(['title' => 'A Target', 'lead_singer_id' => null, 'transition_song_id' => null]);
        Song::factory()->forBand($band)->active()->create([
            'title' => 'B Song',
            'lead_singer_id' => $member->id,
            'transition_song_id' => $target->id,
        ]);

        $resp = $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/songs");

        $resp->assertOk()
            ->assertJsonPath('songs.1.lead_singer.id', $member->id)
            ->assertJsonPath('songs.1.transition_song.id', $target->id)
            ->assertJsonPath('songs.1.transition_song.title', 'A Target')
            ->assertJsonStructure(['songs' => ['1' => ['lead_singer' => ['id', 'display_name'], 'transition_song' => ['id', 'title', 'artist']]]]);
    }
}
