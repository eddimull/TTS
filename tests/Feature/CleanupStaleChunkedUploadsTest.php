<?php

namespace Tests\Feature;

use App\Jobs\CleanupStaleChunkedUploads;
use App\Models\ChunkedUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupStaleChunkedUploadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_cleans_up_uploads_older_than_24_hours()
    {
        $user = User::factory()->create();

        // Create a stale upload (>24 hours old)
        $staleUpload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'status' => 'uploading',
            'last_chunk_at' => now()->subHours(25),
        ]);

        // Create chunk directory
        $disk = Storage::disk('local');
        $disk->put("chunks/{$staleUpload->upload_id}/0", 'test data');

        // Run cleanup job
        $job = new CleanupStaleChunkedUploads();
        $job->handle();

        // Verify upload was marked as failed
        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $staleUpload->upload_id,
            'status' => 'failed',
        ]);

        // Verify chunks were deleted
        Storage::disk('local')->assertMissing("chunks/{$staleUpload->upload_id}/0");
    }

    public function test_does_not_clean_up_recent_uploads()
    {
        $user = User::factory()->create();

        // Create a recent upload (<24 hours old)
        $recentUpload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'status' => 'uploading',
            'last_chunk_at' => now()->subHours(12),
        ]);

        // Create chunk directory
        $disk = Storage::disk('local');
        $disk->put("chunks/{$recentUpload->upload_id}/0", 'test data');

        // Run cleanup job
        $job = new CleanupStaleChunkedUploads();
        $job->handle();

        // Verify upload was NOT marked as failed
        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $recentUpload->upload_id,
            'status' => 'uploading',
        ]);

        // Verify chunks were NOT deleted
        Storage::disk('local')->assertExists("chunks/{$recentUpload->upload_id}/0");
    }

    public function test_does_not_clean_up_completed_uploads()
    {
        $user = User::factory()->create();

        // Create a completed upload (even if old)
        $completedUpload = ChunkedUpload::factory()->completed()->create([
            'user_id' => $user->id,
            'last_chunk_at' => now()->subHours(48),
        ]);

        // Create chunk directory (shouldn't exist for completed, but testing)
        $disk = Storage::disk('local');
        $disk->put("chunks/{$completedUpload->upload_id}/0", 'test data');

        // Run cleanup job
        $job = new CleanupStaleChunkedUploads();
        $job->handle();

        // Verify upload status unchanged
        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $completedUpload->upload_id,
            'status' => 'completed',
        ]);

        // Chunks should still exist
        Storage::disk('local')->assertExists("chunks/{$completedUpload->upload_id}/0");
    }

    public function test_cleans_up_uploads_with_null_last_chunk_at()
    {
        $user = User::factory()->create();

        // Create a stale upload with null last_chunk_at
        $staleUpload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'status' => 'initiated',
            'last_chunk_at' => null,
            'created_at' => now()->subHours(30),
        ]);

        // Create chunk directory
        $disk = Storage::disk('local');
        $disk->put("chunks/{$staleUpload->upload_id}/0", 'test data');

        // Run cleanup job
        $job = new CleanupStaleChunkedUploads();
        $job->handle();

        // Verify upload was marked as failed
        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $staleUpload->upload_id,
            'status' => 'failed',
        ]);

        // Verify chunks were deleted
        Storage::disk('local')->assertMissing("chunks/{$staleUpload->upload_id}/0");
    }

    public function test_cleans_up_multiple_stale_uploads()
    {
        $user = User::factory()->create();

        // Create multiple stale uploads
        $staleUpload1 = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'status' => 'uploading',
            'last_chunk_at' => now()->subHours(25),
        ]);

        $staleUpload2 = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'status' => 'uploading',
            'last_chunk_at' => now()->subHours(48),
        ]);

        $disk = Storage::disk('local');
        $disk->put("chunks/{$staleUpload1->upload_id}/0", 'test data');
        $disk->put("chunks/{$staleUpload2->upload_id}/0", 'test data');

        // Run cleanup job
        $job = new CleanupStaleChunkedUploads();
        $job->handle();

        // Verify both were marked as failed
        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $staleUpload1->upload_id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $staleUpload2->upload_id,
            'status' => 'failed',
        ]);

        // Verify chunks were deleted
        Storage::disk('local')->assertMissing("chunks/{$staleUpload1->upload_id}/0");
        Storage::disk('local')->assertMissing("chunks/{$staleUpload2->upload_id}/0");
    }

    public function test_handles_missing_chunk_directories_gracefully()
    {
        $user = User::factory()->create();

        // Create a stale upload without chunk directory
        $staleUpload = ChunkedUpload::factory()->create([
            'user_id' => $user->id,
            'status' => 'uploading',
            'last_chunk_at' => now()->subHours(30),
        ]);

        // Don't create chunk directory

        // Run cleanup job (should not throw exception)
        $job = new CleanupStaleChunkedUploads();
        $job->handle();

        // Verify upload was still marked as failed
        $this->assertDatabaseHas('chunked_uploads', [
            'upload_id' => $staleUpload->upload_id,
            'status' => 'failed',
        ]);
    }
}
