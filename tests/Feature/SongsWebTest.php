<?php

namespace Tests\Feature;

use App\Models\BandMembers;
use App\Models\Bands;
use App\Models\Charts;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SongsWebTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        return [$user, $band];
    }

    private function makeMemberWithWrite(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);
        setPermissionsTeamId($band->id);
        $user->givePermissionTo('read:songs');
        $user->givePermissionTo('write:songs');
        setPermissionsTeamId(0);

        return [$user, $band];
    }

    public function test_owner_can_create_a_song(): void
    {
        [$user, $band] = $this->makeOwner();

        $resp = $this->actingAs($user)->postJson('/songs', [
            'band_id' => $band->id,
            'title'   => 'Superstition',
            'artist'  => 'Stevie Wonder',
            'bpm'     => 100,
            'active'  => true,
        ]);

        $resp->assertCreated()->assertJsonPath('title', 'Superstition');
        $this->assertDatabaseHas('songs', ['band_id' => $band->id, 'title' => 'Superstition']);
    }

    public function test_member_with_write_permission_can_create_a_song(): void
    {
        [$user, $band] = $this->makeMemberWithWrite();

        $this->actingAs($user)->postJson('/songs', [
            'band_id' => $band->id,
            'title'   => 'My Girl',
            'active'  => true,
        ])->assertCreated();
    }

    public function test_member_without_write_permission_cannot_create_a_song(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson('/songs', [
            'band_id' => $band->id,
            'title'   => 'Nope',
        ])->assertForbidden();
    }

    public function test_store_requires_a_title(): void
    {
        [$user, $band] = $this->makeOwner();

        $this->actingAs($user)->postJson('/songs', ['band_id' => $band->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_owner_can_update_a_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create(['title' => 'Old']);

        $this->actingAs($user)->patchJson("/songs/{$song->id}", [
            'title'  => 'New Title',
            'active' => true,
        ])->assertOk()->assertJsonPath('title', 'New Title');
    }

    public function test_only_owner_can_delete_a_song(): void
    {
        [$member, $band] = $this->makeMemberWithWrite();
        $song = Song::factory()->forBand($band)->create();

        $this->actingAs($member)->deleteJson("/songs/{$song->id}")->assertForbidden();

        $owner = User::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);
        $this->actingAs($owner)->deleteJson("/songs/{$song->id}")->assertOk();
        $this->assertDatabaseMissing('songs', ['id' => $song->id]);
    }

    public function test_store_rejects_cross_band_transition_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $foreign = Song::factory()->forBand(Bands::factory()->create())->create();

        $this->actingAs($user)->postJson('/songs', [
            'band_id' => $band->id,
            'title' => 'Has Bad Transition',
            'transition_song_id' => $foreign->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['transition_song_id']);
    }

    public function test_update_rejects_cross_band_transition_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();
        $foreign = Song::factory()->forBand(Bands::factory()->create())->create();

        $this->actingAs($user)->patchJson("/songs/{$song->id}", [
            'title' => $song->title,
            'transition_song_id' => $foreign->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['transition_song_id']);
    }

    public function test_index_includes_linked_charts_for_songs(): void
    {
        [$user, $band] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();
        Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id, 'title' => 'Horn Chart']);

        $this->actingAs($user)->get("/songs?band_id={$band->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Songs/Index')
                ->where('songs.0.charts.0.title', 'Horn Chart'));
    }
}
