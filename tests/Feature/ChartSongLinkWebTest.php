<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Charts;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChartSongLinkWebTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        return [$user, $band];
    }

    public function test_edit_page_provides_band_songs(): void
    {
        [$user, $band] = $this->makeOwner();
        Song::factory()->forBand($band)->create(['title' => 'My Girl']);
        $chart = Charts::factory()->create(['band_id' => $band->id]);

        $this->actingAs($user)->get("/charts/{$chart->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Charts/Edit')
                ->has('songs', 1)
                ->where('songs.0.title', 'My Girl'));
    }

    public function test_update_links_a_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();
        $chart = Charts::factory()->create(['band_id' => $band->id]);

        $this->actingAs($user)->post("/charts/{$chart->id}", [
            'title' => $chart->title,
            'composer' => $chart->composer,
            'public' => false,
            'description' => '',
            'song_id' => $song->id,
        ]);

        $this->assertDatabaseHas('charts', ['id' => $chart->id, 'song_id' => $song->id]);
    }

    public function test_update_rejects_cross_band_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $foreignSong = Song::factory()->forBand(Bands::factory()->create())->create();
        $chart = Charts::factory()->create(['band_id' => $band->id]);

        $this->actingAs($user)->from("/charts/{$chart->id}/edit")->post("/charts/{$chart->id}", [
            'title' => $chart->title,
            'composer' => $chart->composer,
            'public' => false,
            'description' => '',
            'song_id' => $foreignSong->id,
        ])->assertSessionHasErrors(['song_id']);

        $this->assertDatabaseHas('charts', ['id' => $chart->id, 'song_id' => null]);
    }

    public function test_show_page_includes_linked_song(): void
    {
        [$user, $band] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create(['title' => 'My Girl']);
        $chart = Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id]);

        $this->actingAs($user)->get("/charts/{$chart->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Charts/Show')
                ->where('chart.song.title', 'My Girl'));
    }
}
