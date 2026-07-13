<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Charts;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongChartLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_can_be_linked_to_a_song(): void
    {
        $band = Bands::factory()->create();
        $song = Song::factory()->forBand($band)->create();
        $chart = Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id]);

        $this->assertTrue($chart->song->is($song));
        $this->assertTrue($song->charts->first()->is($chart));
    }

    public function test_deleting_a_song_nulls_the_chart_link_without_deleting_the_chart(): void
    {
        $band = Bands::factory()->create();
        $song = Song::factory()->forBand($band)->create();
        $chart = Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id]);

        $song->delete();

        $this->assertDatabaseHas('charts', ['id' => $chart->id, 'song_id' => null]);
    }
}
