<?php

namespace Tests\Feature;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\ChunkedUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_can_initiate_chunked_upload()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/chunked-uploads/initiate', [
            'filename' => 'large-video.mp4',
            'filesize' => 100000000, // 100MB
            'mime_type' => 'video/mp4',
            'total_chunks' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['upload_id']);

        $this->assertDatabaseHas('chunked_uploads', [
            'filename' => 'large-video.mp4',
            'filesize' => 100000000,
            'mime_type' => 'video/mp4',
            'total_chunks' => 50,
            'status' => 'initiated',
            'user_id' => $user->id,
        ]);
    }

    public function test_initiate_validates_required_fields()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chunked-uploads/initiate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filename', 'filesize', 'mime_type', 'total_chunks']);
    }

    public function test_initiate_rejects_files_exceeding_5gb()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chunked-uploads/initiate', [
            'filename' => 'huge-video.mp4',
            'filesize' => 6000000000, // 6GB
            'mime_type' => 'video/mp4',
            'total_chunks' => 3000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filesize']);
    }

    public function test_can_upload_chunk()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $upload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'total_chunks' => 3,
        ]);

        $chunk = UploadedFile::fake()->create('chunk.bin', 2048); // 2MB chunk

        $response = $this->actingAs($user)->postJson(
            "/api/chunked-uploads/{$upload->upload_id}/chunk",
            [
                'chunk' => $chunk,
                'chunk_index' => 0,
            ]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['progress', 'chunks_uploaded']);

        Storage::disk('local')->assertExists("chunks/{$upload->upload_id}/0");

        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $upload->upload_id,
            'chunks_uploaded' => 1,
            'status' => 'uploading',
        ]);
    }

    public function test_upload_chunk_validates_chunk_index()
    {
        $user = User::factory()->create();
        $upload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'total_chunks' => 3,
        ]);

        $chunk = UploadedFile::fake()->create('chunk.bin', 2048);

        $response = $this->actingAs($user)->postJson(
            "/api/chunked-uploads/{$upload->upload_id}/chunk",
            [
                'chunk' => $chunk,
                'chunk_index' => 999, // Invalid index
            ]
        );

        $response->assertStatus(400)
            ->assertJson(['error' => 'Invalid chunk index']);
    }

    public function test_cannot_upload_chunk_for_another_users_upload()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $upload = ChunkedUpload::factory()->create([
            'user_id' => $user1->id,
        ]);

        $chunk = UploadedFile::fake()->create('chunk.bin', 2048);

        $response = $this->actingAs($user2)->postJson(
            "/api/chunked-uploads/{$upload->upload_id}/chunk",
            [
                'chunk' => $chunk,
                'chunk_index' => 0,
            ]
        );

        $response->assertStatus(404);
    }

    public function test_can_complete_upload_and_merge_chunks()
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $upload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'total_chunks' => 3,
            'chunks_uploaded' => 3,
            'mime_type' => 'video/mp4',
        ]);

        // Create mock chunks
        $disk = Storage::disk('local');
        for ($i = 0; $i < 3; $i++) {
            $disk->put("chunks/{$upload->upload_id}/{$i}", "chunk{$i}data");
        }

        $response = $this->actingAs($user)->postJson(
            "/api/chunked-uploads/{$upload->upload_id}/complete"
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['media']);

        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $upload->upload_id,
            'status' => 'completed',
        ]);

        // Verify chunks were cleaned up
        Storage::disk('local')->assertMissing("chunks/{$upload->upload_id}/0");
        Storage::disk('local')->assertMissing("chunks/{$upload->upload_id}/1");
        Storage::disk('local')->assertMissing("chunks/{$upload->upload_id}/2");
    }

    public function test_complete_fails_when_chunks_are_missing()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $upload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'total_chunks' => 5,
            'chunks_uploaded' => 3, // Missing 2 chunks
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/chunked-uploads/{$upload->upload_id}/complete"
        );

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Missing chunks',
                'expected' => 5,
                'received' => 3,
            ]);
    }

    public function test_complete_fails_when_chunk_file_is_missing()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['band_id' => $band->id, 'user_id' => $user->id]);

        $upload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'total_chunks' => 3,
            'chunks_uploaded' => 3,
        ]);

        // Only create 2 chunks, not 3
        $disk = Storage::disk('local');
        $disk->put("chunks/{$upload->upload_id}/0", "chunk0data");
        $disk->put("chunks/{$upload->upload_id}/1", "chunk1data");
        // Missing chunk 2

        $response = $this->actingAs($user)->postJson(
            "/api/chunked-uploads/{$upload->upload_id}/complete"
        );

        $response->assertStatus(500);

        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $upload->upload_id,
            'status' => 'failed',
        ]);
    }

    public function test_can_get_upload_status()
    {
        $user = User::factory()->create();
        $upload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'total_chunks' => 10,
            'chunks_uploaded' => 5,
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/chunked-uploads/{$upload->upload_id}"
        );

        $response->assertStatus(200)
            ->assertJson([
                'upload_id' => $upload->upload_id,
                'filename' => $upload->filename,
                'total_chunks' => 10,
                'chunks_uploaded' => 5,
                'progress' => 50.0,
            ]);
    }

    public function test_cannot_get_status_for_another_users_upload()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $upload = ChunkedUpload::factory()->create([
            'user_id' => $user1->id,
        ]);

        $response = $this->actingAs($user2)->getJson(
            "/api/chunked-uploads/{$upload->upload_id}"
        );

        $response->assertStatus(404);
    }

    public function test_requires_authentication_for_all_endpoints()
    {
        $response = $this->postJson('/api/chunked-uploads/initiate', []);
        $response->assertStatus(401);

        $response = $this->getJson('/api/chunked-uploads/fake-uuid');
        $response->assertStatus(401);

        $response = $this->postJson('/api/chunked-uploads/fake-uuid/chunk', []);
        $response->assertStatus(401);

        $response = $this->postJson('/api/chunked-uploads/fake-uuid/complete');
        $response->assertStatus(401);
    }
}
