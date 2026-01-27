<?php

namespace App\Jobs;

use App\Models\ChunkedUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupStaleChunkedUploads implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $threshold = now()->subHours(24);

        Log::info('Starting cleanup of stale chunked uploads', [
            'threshold' => $threshold->toDateTimeString(),
        ]);

        // Find stale uploads that are not completed and haven't been updated in 24 hours
        $staleUploads = ChunkedUpload::where('status', '!=', 'completed')
            ->where(function ($query) use ($threshold) {
                $query->where('last_chunk_at', '<', $threshold)
                    ->orWhere(function ($q) use ($threshold) {
                        $q->whereNull('last_chunk_at')
                            ->where('created_at', '<', $threshold);
                    });
            })
            ->get();

        $cleanedCount = 0;
        $disk = Storage::disk('local');

        foreach ($staleUploads as $upload) {
            try {
                // Delete chunk directory from storage
                $chunkDirectory = "chunks/{$upload->upload_id}";
                if ($disk->exists($chunkDirectory)) {
                    $disk->deleteDirectory($chunkDirectory);
                    Log::info('Deleted chunk directory', [
                        'upload_id' => $upload->upload_id,
                        'directory' => $chunkDirectory,
                    ]);
                }

                // Mark upload as failed
                $upload->update(['status' => 'failed']);

                $cleanedCount++;

                Log::info('Cleaned up stale upload', [
                    'upload_id' => $upload->upload_id,
                    'filename' => $upload->filename,
                    'age_hours' => now()->diffInHours($upload->last_chunk_at ?? $upload->created_at),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to cleanup stale upload', [
                    'upload_id' => $upload->upload_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('Completed cleanup of stale chunked uploads', [
            'cleaned_count' => $cleanedCount,
            'total_stale' => $staleUploads->count(),
        ]);
    }
}
