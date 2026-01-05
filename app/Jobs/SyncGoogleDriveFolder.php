<?php

namespace App\Jobs;

use App\Models\GoogleDriveFolder;
use App\Services\GoogleDriveSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleDriveFolder implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 3600; // 1 hour for large folders
    public $tries = 3;
    public $backoff = [60, 300, 900]; // Retry after 1min, 5min, 15min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GoogleDriveFolder $folder,
        public string $syncType = 'manual'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GoogleDriveSyncService $syncService): void
    {
        try {
            Log::info('Starting Google Drive folder sync', [
                'folder_id' => $this->folder->id,
                'google_folder_id' => $this->folder->google_folder_id,
                'sync_type' => $this->syncType,
            ]);

            $stats = $syncService->syncFolder($this->folder, $this->syncType);

            Log::info('Google Drive folder sync completed', [
                'folder_id' => $this->folder->id,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Google Drive folder sync job failed', [
                'folder_id' => $this->folder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Google Drive sync job failed permanently', [
            'folder_id' => $this->folder->id,
            'error' => $exception->getMessage(),
        ]);

        // Update connection status
        $this->folder->connection->update([
            'sync_status' => 'error',
            'last_sync_error' => 'Sync failed: ' . $exception->getMessage(),
        ]);
    }
}
