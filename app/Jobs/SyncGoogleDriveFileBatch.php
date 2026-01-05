<?php

namespace App\Jobs;

use App\Models\GoogleDriveConnection;
use App\Models\GoogleDriveFolder;
use App\Services\GoogleDriveSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleDriveFileBatch implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 600; // 10 minutes per batch
    public $tries = 3;
    public $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GoogleDriveFolder $folder,
        public array $filesBatch,
        public string $localPath,
        public int $batchNumber,
        public int $totalBatches
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GoogleDriveSyncService $syncService): void
    {
        try {
            Log::info('Starting Google Drive file batch sync', [
                'folder_id' => $this->folder->id,
                'batch' => "{$this->batchNumber}/{$this->totalBatches}",
                'file_count' => count($this->filesBatch),
            ]);

            $stats = $syncService->syncFileBatch(
                $this->folder,
                $this->filesBatch,
                $this->localPath
            );

            Log::info('Google Drive file batch sync completed', [
                'folder_id' => $this->folder->id,
                'batch' => "{$this->batchNumber}/{$this->totalBatches}",
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Google Drive file batch sync failed', [
                'folder_id' => $this->folder->id,
                'batch' => "{$this->batchNumber}/{$this->totalBatches}",
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
        Log::error('Google Drive batch sync job failed permanently', [
            'folder_id' => $this->folder->id,
            'batch' => "{$this->batchNumber}/{$this->totalBatches}",
            'error' => $exception->getMessage(),
        ]);
    }
}
