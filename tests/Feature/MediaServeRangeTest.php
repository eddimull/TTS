<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\MediaFile;
use App\Models\BandOwners;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Regression tests for BANDMATE-APP-2 (Sentry).
 *
 * iOS AVPlayer refuses to play video from media.serve because the response
 * ignores the Range header and carries no Content-Length
 * ("CoreMediaErrorDomain -12939 - byte range and no content length").
 * The endpoint must honor byte ranges (206 + Content-Range) and always
 * advertise Accept-Ranges and Content-Length.
 */
class MediaServeRangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Bands $band;
    protected MediaFile $media;

    /** Known 26-byte payload standing in for a video file. */
    protected string $content = 'abcdefghijklmnopqrstuvwxyz';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        config(['filesystems.default' => 's3']);

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create(['site_name' => 'test-band']);
        BandOwners::create([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
        ]);

        $path = 'test-band/media/clip.mp4';
        Storage::disk('s3')->put($path, $this->content);

        $this->media = MediaFile::factory()->create([
            'band_id'         => $this->band->id,
            'filename'        => 'clip.mp4',
            'stored_filename' => $path,
            'mime_type'       => 'video/mp4',
            'media_type'      => 'video',
            'file_size'       => strlen($this->content),
        ]);
    }

    public function test_serve_without_range_returns_200_with_content_length_and_accept_ranges(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('media.serve', $this->media->id));

        $response->assertStatus(200);
        $response->assertHeader('Accept-Ranges', 'bytes');
        $response->assertHeader('Content-Length', (string) strlen($this->content));
        $response->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_serve_with_range_returns_206_with_requested_bytes(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('media.serve', $this->media->id), ['Range' => 'bytes=0-4']);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 0-4/26');
        $response->assertHeader('Content-Length', '5');
        $this->assertSame('abcde', $response->streamedContent());
    }

    public function test_serve_with_open_ended_range_returns_file_tail(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('media.serve', $this->media->id), ['Range' => 'bytes=20-']);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 20-25/26');
        $response->assertHeader('Content-Length', '6');
        $this->assertSame('uvwxyz', $response->streamedContent());
    }

    public function test_serve_with_unsatisfiable_range_returns_416(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('media.serve', $this->media->id), ['Range' => 'bytes=26-']);

        $response->assertStatus(416);
        $response->assertHeader('Content-Range', 'bytes */26');
    }

    public function test_mobile_serve_honors_range_requests(): void
    {
        $token = $this->user->createToken('test-device')->plainTextToken;

        $response = $this->get(
            route('mobile.media.serve', ['band' => $this->band->id, 'media' => $this->media->id]),
            [
                'Authorization' => "Bearer {$token}",
                'X-Band-ID'     => $this->band->id,
                'Range'         => 'bytes=0-4',
            ],
        );

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 0-4/26');
        $this->assertSame('abcde', $response->streamedContent());
    }

    public function test_contact_portal_serve_honors_range_requests(): void
    {
        $contact = Contacts::factory()->create([
            'band_id'   => $this->band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'enable_portal_media_access' => true,
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        Events::factory()->create([
            'eventable_type'             => Bookings::class,
            'eventable_id'               => $booking->id,
            'media_folder_path'          => 'events/portal-range-test',
            'enable_portal_media_access' => true,
        ]);

        $this->media->update(['folder_path' => 'events/portal-range-test']);

        $response = $this->actingAs($contact, 'contact')->get(
            route('portal.media.serve', $this->media->id),
            ['Range' => 'bytes=0-4'],
        );

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 0-4/26');
        $this->assertSame('abcde', $response->streamedContent());
    }
}
