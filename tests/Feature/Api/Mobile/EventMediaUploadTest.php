<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\ChunkedUpload;
use App\Models\EventTypes;
use App\Models\Events;
use App\Models\User;
use App\Services\MediaLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EventMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private function setup_band_event(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $eventType = EventTypes::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id'               => $booking->id,
            'eventable_type'             => 'App\\Models\\Bookings',
            'event_type_id'              => $eventType->id,
            'date'                       => now()->addDays(7)->format('Y-m-d'),
            'title'                      => 'Test Gig',
            'media_folder_path'          => null,
            'enable_portal_media_access' => true,
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'booking', 'event', 'token');
    }

    public function test_completing_event_upload_creates_folder_and_associates_media(): void
    {
        Storage::fake('s3');
        ['user' => $user, 'band' => $band, 'event' => $event] = $this->setup_band_event();

        $upload = ChunkedUpload::factory()->create([
            'user_id'        => $user->id,
            'total_chunks'   => 1,
            'chunks_uploaded'=> 1,
            'mime_type'      => 'image/jpeg',
            'filename'       => 'shot.jpg',
            'folder_path'    => null,
            'event_id'       => $event->id,
        ]);

        Storage::disk('local')->put("chunks/{$upload->upload_id}/0", 'chunkdata');

        $response = $this->actingAs($user)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson(
                "/api/mobile/bands/{$band->id}/media/upload/{$upload->upload_id}/complete"
            );

        $response->assertStatus(200)->assertJsonStructure(['media' => ['id', 'folder_path']]);

        $event->refresh();
        $this->assertNotNull($event->media_folder_path, 'event folder should be created');

        $mediaId = $response->json('media.id');
        $this->assertDatabaseHas('media_files', [
            'id'          => $mediaId,
            'folder_path' => $event->media_folder_path,
        ]);
        $this->assertDatabaseHas('media_associations', [
            'media_file_id'   => $mediaId,
            'associable_type' => 'App\\Models\\Events',
            'associable_id'   => $event->id,
        ]);
    }

    public function test_second_event_upload_reuses_existing_folder(): void
    {
        Storage::fake('s3');
        ['user' => $user, 'band' => $band, 'event' => $event] = $this->setup_band_event();
        $event->update(['media_folder_path' => '2026/07/test-gig']);

        $upload = ChunkedUpload::factory()->create([
            'user_id'        => $user->id,
            'total_chunks'   => 1,
            'chunks_uploaded'=> 1,
            'mime_type'      => 'image/jpeg',
            'filename'       => 'shot2.jpg',
            'folder_path'    => null,
            'event_id'       => $event->id,
        ]);
        Storage::disk('local')->put("chunks/{$upload->upload_id}/0", 'chunkdata');

        $this->actingAs($user)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson(
                "/api/mobile/bands/{$band->id}/media/upload/{$upload->upload_id}/complete"
            )->assertStatus(200);

        $event->refresh();
        $this->assertEquals('2026/07/test-gig', $event->media_folder_path);
    }

    public function test_folder_creation_failure_does_not_abort_upload(): void
    {
        Storage::fake('s3');
        ['user' => $user, 'band' => $band, 'event' => $event] = $this->setup_band_event();

        // Force the (lazy) event-folder creation to blow up. The upload has
        // already been merged, stored on S3, and persisted by this point, so a
        // folder failure must be non-fatal: the media stays valid + associated.
        $mock = Mockery::mock(MediaLibraryService::class)->makePartial();
        $mock->shouldReceive('createEventFolder')
            ->andThrow(new \RuntimeException('boom'));
        $this->app->instance(MediaLibraryService::class, $mock);

        $upload = ChunkedUpload::factory()->create([
            'user_id'        => $user->id,
            'total_chunks'   => 1,
            'chunks_uploaded'=> 1,
            'mime_type'      => 'image/jpeg',
            'filename'       => 'shot3.jpg',
            'folder_path'    => null,
            'event_id'       => $event->id,
        ]);
        Storage::disk('local')->put("chunks/{$upload->upload_id}/0", 'chunkdata');

        $response = $this->actingAs($user)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson(
                "/api/mobile/bands/{$band->id}/media/upload/{$upload->upload_id}/complete"
            );

        $response->assertStatus(200);

        // Upload still completes; the event folder simply stays unset.
        $event->refresh();
        $this->assertNull($event->media_folder_path);

        $mediaId = $response->json('media.id');
        $this->assertDatabaseHas('media_files', ['id' => $mediaId]);
        $this->assertDatabaseHas('media_associations', [
            'media_file_id'   => $mediaId,
            'associable_type' => 'App\\Models\\Events',
            'associable_id'   => $event->id,
        ]);
    }

    public function test_event_detail_returns_associated_media(): void
    {
        Storage::fake('s3');
        ['user' => $user, 'band' => $band, 'event' => $event] = $this->setup_band_event();
        $event->update(['media_folder_path' => '2026/07/test-gig']);

        $media = \App\Models\MediaFile::factory()->create([
            'band_id'     => $band->id,
            'user_id'     => $user->id,
            'folder_path' => '2026/07/test-gig',
            'filename'    => 'live.jpg',
            'media_type'  => 'image',
            'mime_type'   => 'image/jpeg',
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/events/{$event->key}");

        $response->assertStatus(200)
            ->assertJsonStructure(['event' => ['media' => [['id', 'filename', 'media_type', 'mime_type', 'file_size', 'formatted_size', 'thumbnail_url', 'created_at']]]]);

        $ids = collect($response->json('event.media'))->pluck('id');
        $this->assertTrue($ids->contains($media->id), 'event media should include the file in the folder');
    }

    public function test_upload_status_returns_progress(): void
    {
        ['user' => $user, 'band' => $band] = $this->setup_band_event();

        $upload = ChunkedUpload::factory()->create([
            'user_id'         => $user->id,
            'total_chunks'    => 4,
            'chunks_uploaded' => 2,
            'status'          => 'uploading',
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/media/upload/{$upload->upload_id}");

        $response->assertStatus(200)->assertJson([
            'upload_id'       => $upload->upload_id,
            'total_chunks'    => 4,
            'chunks_uploaded' => 2,
            'status'          => 'uploading',
        ]);
    }
}
