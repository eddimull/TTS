<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Charts;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileChartSongLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-device', ['mobile', 'read:charts', 'write:charts'])->plainTextToken;

        return [
            'band' => $band,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'X-Band-ID' => $band->id,
                'Accept' => 'application/json',
            ],
        ];
    }

    public function test_chart_can_be_created_with_a_linked_song(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();

        $resp = $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/charts", [
            'title' => 'Horn Chart',
            'song_id' => $song->id,
        ]);

        $resp->assertCreated()->assertJsonPath('chart.song.id', $song->id);
        $this->assertDatabaseHas('charts', ['title' => 'Horn Chart', 'song_id' => $song->id]);
    }

    public function test_chart_create_rejects_song_from_another_band(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $foreignSong = Song::factory()->forBand(Bands::factory()->create())->create();

        $this->withHeaders($headers)->postJson("/api/mobile/bands/{$band->id}/charts", [
            'title' => 'Sneaky',
            'song_id' => $foreignSong->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['song_id']);
    }

    public function test_chart_can_be_relinked_and_unlinked_via_patch(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create();
        $chart = Charts::factory()->create(['band_id' => $band->id]);

        $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/charts/{$chart->id}", [
            'song_id' => $song->id,
        ])->assertOk()->assertJsonPath('chart.song.id', $song->id);

        $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/charts/{$chart->id}", [
            'song_id' => null,
        ])->assertOk()->assertJsonPath('chart.song', null);
    }

    public function test_patch_rejects_chart_from_another_band(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $foreignChart = Charts::factory()->create(['band_id' => Bands::factory()->create()->id]);

        $this->withHeaders($headers)->patchJson("/api/mobile/bands/{$band->id}/charts/{$foreignChart->id}", [
            'title' => 'Hijack',
        ])->assertNotFound();
    }

    public function test_chart_list_includes_linked_song(): void
    {
        ['band' => $band, 'headers' => $headers] = $this->makeOwner();
        $song = Song::factory()->forBand($band)->create(['title' => 'My Girl']);
        Charts::factory()->create(['band_id' => $band->id, 'song_id' => $song->id]);

        $this->withHeaders($headers)->getJson("/api/mobile/bands/{$band->id}/charts")
            ->assertOk()->assertJsonPath('charts.0.song.title', 'My Girl');
    }
}
