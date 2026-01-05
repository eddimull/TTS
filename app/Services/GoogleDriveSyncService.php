<?php

namespace App\Services;

use App\Models\GoogleDriveConnection;
use App\Models\GoogleDriveFolder;
use App\Models\GoogleDriveSyncLog;
use App\Models\MediaFile;
use App\Models\BandStorageQuota;
use Google\Service\Drive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleDriveSyncService
{
    public function __construct(
        protected GoogleDriveOAuthService $oauthService,
        protected MediaLibraryService $mediaService
    ) {}

    /**
     * Browse folders in Google Drive (for folder selection UI)
     *
     * @param GoogleDriveConnection $connection
     * @param string|null $parentId
     * @return array
     * @throws \Exception
     */
    public function browseFolders(GoogleDriveConnection $connection, ?string $parentId = null): array
    {
        \Log::info('GoogleDriveSyncService::browseFolders called', [
            'connection_id' => $connection->id,
            'parent_id' => $parentId,
            'token_expired' => $connection->isTokenExpired(),
        ]);

        try {
            $drive = $this->oauthService->getDriveClient($connection);

            // Query for both folders and files
            $query = "trashed=false";
            if ($parentId) {
                $query .= " and '{$parentId}' in parents";
            } else {
                $query .= " and 'root' in parents";
            }

            \Log::info('Google Drive API query', [
                'query' => $query,
                'connection_id' => $connection->id,
            ]);

            $results = $drive->files->listFiles([
                'q' => $query,
                'fields' => 'files(id, name, mimeType, parents)',
                'orderBy' => 'folder,name', // Folders first, then files
                'pageSize' => 100,
            ]);

            $files = $results->getFiles();
            \Log::info('Google Drive API response', [
                'connection_id' => $connection->id,
                'item_count' => count($files),
            ]);

            return array_map(function($file) {
                $isFolder = $file->mimeType === 'application/vnd.google-apps.folder';
                return [
                    'id' => $file->id,
                    'name' => $file->name,
                    'is_folder' => $isFolder,
                    'mime_type' => $file->mimeType,
                    'has_children' => $isFolder, // Only folders can have children
                ];
            }, $files);
        } catch (\Exception $e) {
            \Log::error('GoogleDriveSyncService::browseFolders error', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Sync a specific folder from Drive to local storage
     *
     * @param GoogleDriveFolder $driveFolder
     * @param string $syncType
     * @param bool $useBatching Whether to use batch processing (default: true)
     * @param int $batchSize Number of files per batch (default: 50)
     * @return array
     * @throws \Exception
     */
    public function syncFolder(
        GoogleDriveFolder $driveFolder,
        string $syncType = 'manual',
        bool $useBatching = true,
        int $batchSize = 50
    ): array {
        $connection = $driveFolder->connection;
        $drive = $this->oauthService->getDriveClient($connection);

        // Create sync log
        $log = GoogleDriveSyncLog::create([
            'connection_id' => $connection->id,
            'folder_id' => $driveFolder->id,
            'sync_type' => $syncType,
            'status' => 'started',
            'started_at' => now(),
        ]);

        try {
            if ($useBatching) {
                $stats = $this->syncFolderWithBatching(
                    $drive,
                    $connection,
                    $driveFolder,
                    $driveFolder->google_folder_id,
                    $driveFolder->local_folder_path ?? $driveFolder->google_folder_name,
                    $batchSize
                );
            } else {
                $stats = $this->syncFolderRecursive(
                    $drive,
                    $connection,
                    $driveFolder,
                    $driveFolder->google_folder_id,
                    $driveFolder->local_folder_path ?? $driveFolder->google_folder_name
                );
            }

            // Update log
            $log->update([
                'status' => 'completed',
                'completed_at' => now(),
                'files_checked' => $stats['checked'],
                'files_downloaded' => $stats['downloaded'] ?? 0,
                'files_updated' => $stats['updated'] ?? 0,
                'files_skipped' => $stats['skipped'] ?? 0,
                'bytes_transferred' => $stats['bytes'] ?? 0,
            ]);

            // Update folder sync time
            $driveFolder->update(['last_synced_at' => now()]);
            $connection->update([
                'last_synced_at' => now(),
                'sync_status' => 'success',
                'last_sync_error' => null,
            ]);

            return $stats;

        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            $connection->update([
                'sync_status' => 'error',
                'last_sync_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync folder with batching for memory efficiency
     *
     * @param Drive $drive
     * @param GoogleDriveConnection $connection
     * @param GoogleDriveFolder $driveFolder
     * @param string $driveFolderId
     * @param string $localPath
     * @param int $batchSize
     * @return array
     * @throws \Exception
     */
    protected function syncFolderWithBatching(
        Drive $drive,
        GoogleDriveConnection $connection,
        GoogleDriveFolder $driveFolder,
        string $driveFolderId,
        string $localPath,
        int $batchSize = 50
    ): array {
        \Log::info('Starting batched folder sync', [
            'folder_id' => $driveFolder->id,
            'google_folder_id' => $driveFolderId,
            'batch_size' => $batchSize,
        ]);

        // Collect all file metadata (lightweight)
        $allFiles = $this->collectFileMetadata($drive, $driveFolderId);

        \Log::info('Collected file metadata', [
            'folder_id' => $driveFolder->id,
            'total_files' => count($allFiles),
        ]);

        // Separate files from folders
        $files = [];
        $subfolders = [];

        foreach ($allFiles as $item) {
            if ($item['is_folder']) {
                $subfolders[] = $item;
            } else {
                $files[] = $item;
            }
        }

        // Dispatch batch jobs for files
        $batches = array_chunk($files, $batchSize);
        $totalBatches = count($batches);
        $jobsDispatched = 0;

        foreach ($batches as $index => $batch) {
            \App\Jobs\SyncGoogleDriveFileBatch::dispatch(
                $driveFolder,
                $batch,
                $localPath,
                $index + 1,
                $totalBatches
            );
            $jobsDispatched++;
        }

        // Process subfolders recursively (dispatch separate jobs for each)
        foreach ($subfolders as $subfolder) {
            $subfolderPath = $localPath . '/' . $subfolder['name'];

            \Log::info('Dispatching job for subfolder', [
                'subfolder_name' => $subfolder['name'],
                'subfolder_id' => $subfolder['id'],
            ]);

            // Create a temporary GoogleDriveFolder instance for the subfolder
            // This allows us to track it separately
            $subfolderRecord = GoogleDriveFolder::firstOrCreate(
                [
                    'connection_id' => $connection->id,
                    'google_folder_id' => $subfolder['id'],
                ],
                [
                    'google_folder_name' => $subfolder['name'],
                    'local_folder_path' => $subfolderPath,
                    'auto_sync' => false, // Don't auto-sync sub-folders
                ]
            );

            \App\Jobs\SyncGoogleDriveFolder::dispatch($subfolderRecord, 'manual');
        }

        \Log::info('Batched folder sync initiated', [
            'folder_id' => $driveFolder->id,
            'file_batches_dispatched' => $jobsDispatched,
            'subfolders_dispatched' => count($subfolders),
            'total_files' => count($files),
        ]);

        return [
            'checked' => count($allFiles),
            'batches_dispatched' => $jobsDispatched,
            'subfolders_dispatched' => count($subfolders),
        ];
    }

    /**
     * Collect file metadata without downloading
     *
     * @param Drive $drive
     * @param string $folderId
     * @return array
     * @throws \Exception
     */
    protected function collectFileMetadata(Drive $drive, string $folderId): array
    {
        $allFiles = [];
        $query = "'{$folderId}' in parents and trashed=false";
        $pageToken = null;

        do {
            $params = [
                'q' => $query,
                'fields' => 'nextPageToken, files(id, name, mimeType, size, modifiedTime, md5Checksum)',
                'pageSize' => 100,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $results = $drive->files->listFiles($params);

            foreach ($results->getFiles() as $file) {
                $isFolder = $file->mimeType === 'application/vnd.google-apps.folder';

                $allFiles[] = [
                    'id' => $file->id,
                    'name' => $file->name,
                    'mime_type' => $file->mimeType,
                    'size' => $file->size ?? 0,
                    'modified_time' => $file->modifiedTime,
                    'md5_checksum' => $file->md5Checksum ?? null,
                    'is_folder' => $isFolder,
                ];
            }

            $pageToken = $results->getNextPageToken();

        } while ($pageToken);

        return $allFiles;
    }

    /**
     * Sync a batch of files
     *
     * @param GoogleDriveFolder $driveFolder
     * @param array $filesBatch
     * @param string $localPath
     * @return array
     * @throws \Exception
     */
    public function syncFileBatch(
        GoogleDriveFolder $driveFolder,
        array $filesBatch,
        string $localPath
    ): array {
        $connection = $driveFolder->connection;
        $drive = $this->oauthService->getDriveClient($connection);

        $stats = ['downloaded' => 0, 'updated' => 0, 'skipped' => 0, 'bytes' => 0];

        foreach ($filesBatch as $fileMetadata) {
            try {
                // Skip Google Workspace files
                if (str_starts_with($fileMetadata['mime_type'], 'application/vnd.google-apps.') &&
                    $fileMetadata['mime_type'] !== 'application/vnd.google-apps.folder') {
                    $stats['skipped']++;
                    continue;
                }

                // Check if file already synced
                $existingFile = MediaFile::where('google_drive_file_id', $fileMetadata['id'])
                    ->where('drive_connection_id', $connection->id)
                    ->first();

                if ($existingFile) {
                    // Check if needs update
                    $driveModified = new \DateTime($fileMetadata['modified_time']);
                    if ($existingFile->drive_last_modified &&
                        $existingFile->drive_last_modified->gte($driveModified)) {
                        $stats['skipped']++;
                        continue;
                    }

                    // Fetch full file object from Drive for update
                    $driveFile = $drive->files->get($fileMetadata['id'], [
                        'fields' => 'id, name, mimeType, size, modifiedTime, md5Checksum'
                    ]);

                    $this->downloadAndUpdateFile($drive, $driveFile, $existingFile, $connection, $localPath);
                    $stats['updated']++;
                    $stats['bytes'] += $fileMetadata['size'];
                } else {
                    // Fetch full file object from Drive for download
                    $driveFile = $drive->files->get($fileMetadata['id'], [
                        'fields' => 'id, name, mimeType, size, modifiedTime, md5Checksum'
                    ]);

                    $this->downloadAndCreateFile($drive, $driveFile, $connection, $localPath);
                    $stats['downloaded']++;
                    $stats['bytes'] += $fileMetadata['size'];
                }

                // Free memory after each file
                unset($driveFile);
                gc_collect_cycles();

            } catch (\Exception $e) {
                \Log::error('Failed to sync file in batch', [
                    'file_id' => $fileMetadata['id'],
                    'file_name' => $fileMetadata['name'],
                    'error' => $e->getMessage(),
                ]);
                // Continue with next file instead of failing entire batch
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * Recursive folder sync implementation
     *
     * @param Drive $drive
     * @param GoogleDriveConnection $connection
     * @param GoogleDriveFolder $driveFolder
     * @param string $driveFolderId
     * @param string $localPath
     * @return array
     * @throws \Exception
     */
    protected function syncFolderRecursive(
        Drive $drive,
        GoogleDriveConnection $connection,
        GoogleDriveFolder $driveFolder,
        string $driveFolderId,
        string $localPath
    ): array {
        $stats = ['checked' => 0, 'downloaded' => 0, 'updated' => 0, 'skipped' => 0, 'bytes' => 0];

        // List files in current Drive folder
        $query = "'{$driveFolderId}' in parents and trashed=false";
        $pageToken = null;

        do {
            $params = [
                'q' => $query,
                'fields' => 'nextPageToken, files(id, name, mimeType, size, modifiedTime, md5Checksum)',
                'pageSize' => 100,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $results = $drive->files->listFiles($params);

            foreach ($results->getFiles() as $file) {
                $stats['checked']++;

                // Handle subfolders recursively
                if ($file->mimeType === 'application/vnd.google-apps.folder') {
                    \Log::info('Recursively syncing subfolder', [
                        'folder_name' => $file->name,
                        'folder_id' => $file->id,
                        'parent_path' => $localPath,
                    ]);

                    $subfolderPath = $localPath . '/' . $file->name;
                    $subStats = $this->syncFolderRecursive(
                        $drive,
                        $connection,
                        $driveFolder,
                        $file->id,
                        $subfolderPath
                    );

                    // Merge substats
                    $stats['checked'] += $subStats['checked'];
                    $stats['downloaded'] += $subStats['downloaded'];
                    $stats['updated'] += $subStats['updated'];
                    $stats['skipped'] += $subStats['skipped'];
                    $stats['bytes'] += $subStats['bytes'];

                    continue;
                }

                // Skip Google Workspace files (Docs, Sheets, etc - not downloadable as regular files)
                if (str_starts_with($file->mimeType, 'application/vnd.google-apps.')) {
                    $stats['skipped']++;
                    continue;
                }

                // Check if file already synced
                $existingFile = MediaFile::where('google_drive_file_id', $file->id)
                    ->where('drive_connection_id', $connection->id)
                    ->first();

                if ($existingFile) {
                    // Check if needs update
                    $driveModified = new \DateTime($file->modifiedTime);
                    if ($existingFile->drive_last_modified &&
                        $existingFile->drive_last_modified->gte($driveModified)) {
                        $stats['skipped']++;
                        continue;
                    }

                    // Update existing file
                    $this->downloadAndUpdateFile($drive, $file, $existingFile, $connection, $localPath);
                    $stats['updated']++;
                    $stats['bytes'] += $file->size ?? 0;
                } else {
                    // Download new file
                    $this->downloadAndCreateFile($drive, $file, $connection, $localPath);
                    $stats['downloaded']++;
                    $stats['bytes'] += $file->size ?? 0;
                }
            }

            $pageToken = $results->getNextPageToken();

        } while ($pageToken);

        return $stats;
    }

    /**
     * Download and create new media file
     *
     * @param Drive $drive
     * @param \Google_Service_Drive_DriveFile $driveFile
     * @param GoogleDriveConnection $connection
     * @param string $localPath
     * @return void
     * @throws \Exception
     */
    protected function downloadAndCreateFile(
        Drive $drive,
        $driveFile,
        GoogleDriveConnection $connection,
        string $localPath
    ): void {
        $band = $connection->band;

        // Check quota
        $quota = BandStorageQuota::firstOrCreate(
            ['band_id' => $band->id],
            ['quota_limit' => 5368709120, 'quota_used' => 0]
        );

        $fileSize = $driveFile->size ?? 0;
        if ($fileSize > 0 && !$quota->hasSpace($fileSize)) {
            throw new \Exception("Storage quota exceeded. Cannot download: {$driveFile->name}");
        }

        // Download file content
        $response = $drive->files->get($driveFile->id, ['alt' => 'media']);
        $content = $response->getBody()->getContents();

        // Generate storage path
        $extension = pathinfo($driveFile->name, PATHINFO_EXTENSION);
        $uuid = Str::uuid();
        $filename = $uuid . ($extension ? '.' . $extension : '');
        $storagePath = $band->site_name . '/media';
        $disk = config('filesystems.default');

        // Store file
        Storage::disk($disk)->put("{$storagePath}/{$filename}", $content, 'private');

        // Determine media type
        $mediaType = $this->determineMediaType($driveFile->mimeType);

        // Create media record
        MediaFile::create([
            'band_id' => $band->id,
            'user_id' => $connection->user_id,
            'filename' => $driveFile->name,
            'stored_filename' => "{$storagePath}/{$filename}",
            'mime_type' => $driveFile->mimeType,
            'file_size' => $fileSize,
            'disk' => $disk,
            'media_type' => $mediaType,
            'title' => pathinfo($driveFile->name, PATHINFO_FILENAME),
            'folder_path' => $localPath,
            'source' => 'google_drive',
            'google_drive_file_id' => $driveFile->id,
            'drive_connection_id' => $connection->id,
            'drive_last_modified' => new \DateTime($driveFile->modifiedTime),
        ]);

        // Update quota
        if ($fileSize > 0) {
            $quota->increment('quota_used', $fileSize);
        }
    }

    /**
     * Download and update existing file
     *
     * @param Drive $drive
     * @param \Google_Service_Drive_DriveFile $driveFile
     * @param MediaFile $mediaFile
     * @param GoogleDriveConnection $connection
     * @param string $localPath
     * @return void
     * @throws \Exception
     */
    protected function downloadAndUpdateFile(
        Drive $drive,
        $driveFile,
        MediaFile $mediaFile,
        GoogleDriveConnection $connection,
        string $localPath
    ): void {
        // Check quota for size difference
        $newSize = $driveFile->size ?? 0;
        $oldSize = $mediaFile->file_size;
        $sizeDiff = $newSize - $oldSize;

        if ($sizeDiff > 0) {
            $quota = BandStorageQuota::where('band_id', $mediaFile->band_id)->first();
            if ($quota && !$quota->hasSpace($sizeDiff)) {
                throw new \Exception("Storage quota exceeded. Cannot update: {$driveFile->name}");
            }
        }

        // Delete old file from storage
        if (Storage::disk($mediaFile->disk)->exists($mediaFile->stored_filename)) {
            Storage::disk($mediaFile->disk)->delete($mediaFile->stored_filename);
        }

        // Download new version
        $response = $drive->files->get($driveFile->id, ['alt' => 'media']);
        $content = $response->getBody()->getContents();

        // Store updated file
        Storage::disk($mediaFile->disk)->put($mediaFile->stored_filename, $content, 'private');

        // Update quota
        $quota = BandStorageQuota::where('band_id', $mediaFile->band_id)->first();
        if ($quota) {
            if ($sizeDiff > 0) {
                $quota->increment('quota_used', $sizeDiff);
            } elseif ($sizeDiff < 0) {
                $quota->decrement('quota_used', abs($sizeDiff));
            }
        }

        // Update media record
        $mediaFile->update([
            'file_size' => $newSize,
            'mime_type' => $driveFile->mimeType,
            'drive_last_modified' => new \DateTime($driveFile->modifiedTime),
        ]);
    }

    /**
     * Determine media type from MIME type
     *
     * @param string $mimeType
     * @return string
     */
    protected function determineMediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ])) {
            return 'document';
        }

        return 'other';
    }
}
