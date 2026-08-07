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

    private function makeBandWithSongs(): Bands
    {
        $band = Bands::factory()->create();

        Song::factory()->create(['band_id' => $band->id, 'title' => 'Superstition', 'active' => true]);
        Song::factory()->create(['band_id' => $band->id, 'title' => 'Inactive Tune', 'active' => false]);

        return $band;
    }

    private function makeMemberWithRead(Bands $band): User
    {
        $user = User::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);
        setPermissionsTeamId($band->id);
        $user->givePermissionTo('read:songs');
        setPermissionsTeamId(0);

        return $user;
    }

    public function test_band_member_can_download_song_list_pdf(): void
    {
        $band = $this->makeBandWithSongs();
        $user = $this->makeMemberWithRead($band);

        // Assert the PDF is rendered from HTML containing only active songs,
        // at the expected page format.
        $this->mock(PdfGeneratorService::class)
            ->shouldReceive('generateFromHtml')
            ->once()
            ->withArgs(function (string $html, string $format = 'Letter') {
                return str_contains($html, 'Superstition')
                    && !str_contains($html, 'Inactive Tune')
                    && $format === 'Letter';
            })
            ->andReturn('%PDF-1.4 fake');

        $resp = $this->actingAs($user)->get('/songs/download?band_id=' . $band->id);

        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', $resp->headers->get('content-disposition'));
    }

    public function test_user_outside_band_cannot_download_song_list(): void
    {
        $band = $this->makeBandWithSongs();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get('/songs/download?band_id=' . $band->id)
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $band = $this->makeBandWithSongs();

        $this->get('/songs/download?band_id=' . $band->id)
            ->assertRedirect();
    }
}
