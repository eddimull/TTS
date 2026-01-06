<?php

namespace App\Jobs;

use App\Models\GoogleDriveConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAllGoogleDriveFolders implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300; // 5 minutes (just to dispatch jobs, not actually sync)

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
        Log::info('Starting scheduled Google Drive sync for all connections');

        // Get all active connections with folders that need syncing
        $connections = GoogleDriveConnection::with('folders')
            ->active()
            ->whereHas('folders', function($q) {
                $q->autoSync();
            })
            ->get();

        $totalFoldersDispatched = 0;

        foreach ($connections as $connection) {
            foreach ($connection->folders as $folder) {
                if ($folder->auto_sync) {
                    try {
                        SyncGoogleDriveFolder::dispatch($folder, 'scheduled');
                        $totalFoldersDispatched++;

                        Log::debug('Dispatched scheduled sync job', [
                            'connection_id' => $connection->id,
                            'folder_id' => $folder->id,
                            'google_folder_name' => $folder->google_folder_name,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to dispatch sync job', [
                            'connection_id' => $connection->id,
                            'folder_id' => $folder->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        Log::info('Finished dispatching scheduled Google Drive syncs', [
            'connections_processed' => $connections->count(),
            'folders_dispatched' => $totalFoldersDispatched,
        ]);
    }
}
