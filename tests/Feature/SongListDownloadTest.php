<?php

namespace Tests\Feature;

use App\Models\BandMembers;
use App\Models\Bands;
use App\Models\Song;
use App\Models\User;
use App\Services\PdfGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongListDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnerWithSongs(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        Song::factory()->create(['band_id' => $band->id, 'title' => 'Superstition', 'active' => true]);
        Song::factory()->create(['band_id' => $band->id, 'title' => 'Inactive Tune', 'active' => false]);

        return [$user, $band];
    }

    private function fakePdf(): void
    {
        $mock = $this->mock(PdfGeneratorService::class);
        $mock->shouldReceive('generateFromHtml')->andReturn('%PDF-1.4 fake');
    }

    public function test_band_member_can_download_song_list_pdf(): void
    {
        [$user, $band] = $this->makeOwnerWithSongs();
        $this->fakePdf();

        $resp = $this->actingAs($user)->get('/songs/download?band_id=' . $band->id);

        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', $resp->headers->get('content-disposition'));
    }

    public function test_user_outside_band_cannot_download_song_list(): void
    {
        [, $band] = $this->makeOwnerWithSongs();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get('/songs/download?band_id=' . $band->id)
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        [, $band] = $this->makeOwnerWithSongs();

        $this->get('/songs/download?band_id=' . $band->id)
            ->assertRedirect();
    }
}
