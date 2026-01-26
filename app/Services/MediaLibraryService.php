<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\BandStorageQuota;
use App\Models\EventAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaLibraryService
{
    /**
     * Upload a file to the media library
     *
     * @param \App\Models\Bands $band
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $userId
     * @param array $additionalData
     * @return \App\Models\MediaFile
     * @throws \Exception
     */
    public function uploadFile($band, $file, $userId, $additionalData = [])
    {
        // Check quota
        $quota = BandStorageQuota::firstOrCreate(
            ['band_id' => $band->id],
            [
                'quota_limit' => 50368709120, // 5GB default
                'quota_used' => 0
            ]
        );
        if (!$quota->hasSpace($file->getSize())) {
            throw new \Exception('Storage quota exceeded. Please upgrade your plan or delete some files.');
        }

        // Validate file
        $this->validateFile($file);

        // Determine media type
        $mimeType = $file->getMimeType();
        $mediaType = $this->determineMediaType($mimeType);

        // Generate UUID filename
        $extension = $file->getClientOriginalExtension();
        $uuid = Str::uuid();
        $filename = $uuid . '.' . $extension;

        // Store with band-scoped path
        $storagePath = $band->site_name . '/media';
        $disk = config('filesystems.default');

        // Store the file with private visibility
        $path = $file->storeAs($storagePath, $filename, [
            'disk' => $disk,
            'visibility' => 'private'
        ]);

        // Create folder record if uploading to a folder
        if (!empty($additionalData['folder_path'])) {
            $this->createFolder($band->id, $additionalData['folder_path'], $userId);
        }

        // Create media record
        $mediaFile = MediaFile::create([
            'band_id' => $band->id,
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'stored_filename' => $path,
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'disk' => $disk,
            'media_type' => $mediaType,
            'title' => $additionalData['title'] ?? $file->getClientOriginalName(),
            'description' => $additionalData['description'] ?? null,
            'folder_path' => $additionalData['folder_path'] ?? null,
        ]);

        // Generate thumbnail for images and videos
        if ($mediaType === 'image' || $mediaType === 'video') {
            $this->generateThumbnail($mediaFile);
        }

        // Update quota
        $quota->quota_used += $file->getSize();
        $quota->save();

        return $mediaFile;
    }

    /**
     * Validate the uploaded file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @throws \Exception
     */
    private function validateFile($file)
    {
        $maxSize = 100 * 1024 * 1024; // 100MB
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds maximum allowed (100MB)');
        }

        $allowedMimes = [
            // Images
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            // Videos
            'video/mp4',
            'video/quicktime',
            'video/x-msvideo',
            'video/mpeg',
            // Audio
            'audio/mpeg',
            'audio/wav',
            'audio/mp4',
            'audio/x-m4a',
            'audio/ogg',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ];

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('File type not allowed: ' . $file->getMimeType());
        }
    }

    /**
     * Determine the media type from MIME type
     *
     * @param string $mimeType
     * @return string
     */
    private function determineMediaType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if ($mimeType === 'application/pdf' || str_contains($mimeType, 'document') || str_contains($mimeType, 'excel') || str_contains($mimeType, 'word')) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Generate thumbnail for an image or video
     *
     * @param \App\Models\MediaFile $mediaFile
     */
    private function generateThumbnail($mediaFile)
    {
        $thumbnailPath = str_replace(
            '.' . pathinfo($mediaFile->stored_filename, PATHINFO_EXTENSION),
            '_thumb.jpg',
            $mediaFile->stored_filename
        );

        try {
            \Log::info('Starting thumbnail generation', [
                'media_file_id' => $mediaFile->id,
                'media_type' => $mediaFile->media_type,
                'source_file' => $mediaFile->stored_filename,
                'thumbnail_path' => $thumbnailPath
            ]);

            // Verify source file exists
            if (!Storage::disk($mediaFile->disk)->exists($mediaFile->stored_filename)) {
                throw new \Exception("Source file not found: {$mediaFile->stored_filename}");
            }

            if ($mediaFile->media_type === 'video') {
                // Use FFmpeg for video thumbnails
                $this->generateVideoThumbnail($mediaFile, $thumbnailPath);
            } else if ($mediaFile->media_type === 'image') {
                // Use ImageMagick for image thumbnails
                $this->generateImageThumbnail($mediaFile, $thumbnailPath);
            }

            // Verify thumbnail was created
            if (!Storage::disk($mediaFile->disk)->exists($thumbnailPath)) {
                throw new \Exception("Thumbnail file was not created");
            }

            \Log::info('Thumbnail generated successfully', [
                'media_file_id' => $mediaFile->id,
                'thumbnail_path' => $thumbnailPath,
                'size' => Storage::disk($mediaFile->disk)->size($thumbnailPath)
            ]);

        } catch (\Exception $e) {
            \Log::error('Thumbnail generation failed', [
                'media_file_id' => $mediaFile->id,
                'media_type' => $mediaFile->media_type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback: generate placeholder thumbnail
            $this->generatePlaceholderThumbnail($mediaFile, $thumbnailPath);
        }
    }

    /**
     * Generate thumbnail for a video using FFmpeg
     *
     * @param \App\Models\MediaFile $mediaFile
     * @param string $thumbnailPath
     */
    private function generateVideoThumbnail($mediaFile, $thumbnailPath)
    {
        $ffmpeg = \FFMpeg\FFMpeg::create([
            'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe',
            'timeout'          => 3600,
            'ffmpeg.threads'   => 12,
        ]);

        $video = $ffmpeg->open(Storage::disk($mediaFile->disk)->path($mediaFile->stored_filename));

        // Generate thumbnail at 1 second mark
        $frame = $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(1));
        $frame->save(Storage::disk($mediaFile->disk)->path($thumbnailPath));
    }

    /**
     * Generate thumbnail for an image using Imagick
     *
     * @param \App\Models\MediaFile $mediaFile
     * @param string $thumbnailPath
     */
    private function generateImageThumbnail($mediaFile, $thumbnailPath)
    {
        $imagick = new \Imagick(Storage::disk($mediaFile->disk)->path($mediaFile->stored_filename));

        // Resize to max 400x400 maintaining aspect ratio
        $imagick->thumbnailImage(400, 400, true);

        // Set JPEG quality
        $imagick->setImageCompressionQuality(85);
        $imagick->setImageFormat('jpeg');

        // Save the thumbnail
        $imagick->writeImage(Storage::disk($mediaFile->disk)->path($thumbnailPath));
        $imagick->clear();
        $imagick->destroy();
    }

    /**
     * Generate a placeholder thumbnail when processing fails
     *
     * @param \App\Models\MediaFile $mediaFile
     * @param string $thumbnailPath
     */
    private function generatePlaceholderThumbnail($mediaFile, $thumbnailPath)
    {
        try {
            // Create a simple placeholder image
            $image = imagecreatetruecolor(640, 360);
            $bgColor = imagecolorallocate($image, 200, 200, 200);
            $textColor = imagecolorallocate($image, 100, 100, 100);

            imagefill($image, 0, 0, $bgColor);

            // Add text
            $text = $mediaFile->media_type === 'video' ? 'VIDEO' : 'IMAGE';
            imagestring($image, 5, 280, 175, $text, $textColor);

            // Save as JPEG
            ob_start();
            imagejpeg($image, null, 80);
            $imageData = ob_get_clean();
            imagedestroy($image);

            Storage::disk($mediaFile->disk)->put($thumbnailPath, $imageData);

            \Log::info('Placeholder thumbnail generated', [
                'media_file_id' => $mediaFile->id,
                'thumbnail_path' => $thumbnailPath
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate placeholder thumbnail', [
                'media_file_id' => $mediaFile->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Search media files with filters
     *
     * @param int $bandId
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function search($bandId, $filters = [])
    {
        $query = MediaFile::where('band_id', $bandId);

        // Search by filename, title, description
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('filename', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filter by media type
        if (!empty($filters['media_type'])) {
            $query->where('media_type', $filters['media_type']);
        }

        // Filter by tags
        if (!empty($filters['tags'])) {
            $tagIds = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $query->whereHas('tags', function($q) use ($tagIds) {
                $q->whereIn('media_tags.id', $tagIds);
            });
        }

        // Filter by event associations
        if (!empty($filters['event_id'])) {
            $query->whereHas('associations', function($q) use ($filters) {
                $q->where('associable_type', 'App\\Models\\Events')
                  ->where('associable_id', $filters['event_id']);
            });
        }

        // Filter by booking associations
        if (!empty($filters['booking_id'])) {
            $query->whereHas('associations', function($q) use ($filters) {
                $q->where('associable_type', 'App\\Models\\Bookings')
                  ->where('associable_id', $filters['booking_id']);
            });
        }

        // Filter by folder
        if (isset($filters['folder_path'])) {
            if ($filters['folder_path'] === '') {
                // Root folder - files with no folder path
                $query->whereNull('folder_path');
            } elseif (in_array($filters['folder_path'], ['charts', 'contracts', 'event_uploads'])) {
                // System folders - return empty query, will be handled separately
                return $query->whereRaw('1 = 0'); // Return empty result
            } else {
                // Specific folder
                $query->where('folder_path', $filters['folder_path']);
            }
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query;
    }

    /**
     * Get files from system folders (charts, contracts, event_uploads)
     *
     * @param int $bandId
     * @param string $folderPath
     * @param array $filters
     * @return \Illuminate\Support\Collection
     */
    public function getSystemFolderFiles($bandId, $folderPath, $filters = [])
    {
        $files = collect();

        switch ($folderPath) {
            case 'charts':
                $chartFiles = DB::table('chart_uploads')
                    ->join('charts', 'chart_uploads.chart_id', '=', 'charts.id')
                    ->where('charts.band_id', $bandId)
                    ->select(
                        'chart_uploads.id',
                        'chart_uploads.chart_id',
                        'chart_uploads.name',
                        'chart_uploads.url as stored_filename',
                        'chart_uploads.displayName as filename',
                        'chart_uploads.fileType as mime_type',
                        'charts.title',
                        'chart_uploads.created_at'
                    )
                    ->orderBy('chart_uploads.created_at', 'desc')
                    ->get();

                $files = $chartFiles->map(function ($file) {
                    // Use the existing chart download route with the name field (for route model binding)
                    $chartUrl = url('/charts/' . $file->chart_id . '/chartDownload/' . $file->name);

                    return (object) [
                        'id' => 'chart_' . $file->id,
                        'filename' => $file->filename,
                        'title' => $file->title ?? $file->filename,
                        'stored_filename' => $file->stored_filename,
                        'mime_type' => $file->mime_type,
                        'media_type' => $this->determineMediaType($file->mime_type),
                        'file_size' => 0,
                        'folder_path' => 'charts',
                        'description' => null,
                        'created_at' => $file->created_at,
                        'formatted_size' => '—',
                        'url' => $chartUrl,
                        'is_system_file' => true,
                        'tags' => [],
                    ];
                });
                break;

            case 'contracts':
                // Get contracts from bookings
                $contractFiles = DB::table('contracts')
                    ->join('bookings', function ($join) {
                        $join->on('contracts.contractable_id', '=', 'bookings.id')
                             ->where('contracts.contractable_type', '=', 'App\Models\Bookings');
                    })
                    ->where('bookings.band_id', $bandId)
                    ->whereNotNull('contracts.asset_url')
                    ->select(
                        'contracts.id',
                        'contracts.contractable_id as booking_id',
                        'contracts.asset_url as stored_filename',
                        'bookings.name as title',
                        'bookings.band_id',
                        'contracts.status',
                        'contracts.created_at'
                    )
                    ->orderBy('contracts.created_at', 'desc')
                    ->get();

                $files = $contractFiles->map(function ($file) use ($bandId) {
                    $filename = basename($file->stored_filename);
                    // Use the existing contract download route
                    $contractUrl = url('/bands/' . $bandId . '/booking/' . $file->booking_id . '/contract/download');

                    return (object) [
                        'id' => 'contract_' . $file->id,
                        'filename' => $filename,
                        'title' => $file->title . ' - Contract',
                        'stored_filename' => $file->stored_filename,
                        'mime_type' => 'application/pdf',
                        'media_type' => 'document',
                        'file_size' => 0,
                        'folder_path' => 'contracts',
                        'description' => 'Status: ' . ($file->status ?? 'Unknown'),
                        'created_at' => $file->created_at,
                        'formatted_size' => '—',
                        'url' => $contractUrl,
                        'is_system_file' => true,
                        'tags' => [],
                    ];
                });
                break;

            case 'event_uploads':
                $eventFiles = EventAttachment::whereHas('event', function ($query) use ($bandId) {
                    $query->whereHasMorph('eventable', ['App\Models\Bookings', 'App\Models\BandEvents'], function ($q) use ($bandId) {
                        $q->where('band_id', $bandId);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();

                $files = $eventFiles->map(function ($file) {
                    return (object) [
                        'id' => 'event_upload_' . $file->id,
                        'filename' => $file->filename,
                        'title' => $file->filename,
                        'stored_filename' => $file->stored_filename,
                        'mime_type' => $file->mime_type,
                        'media_type' => $this->determineMediaType($file->mime_type),
                        'file_size' => $file->file_size,
                        'folder_path' => 'event_uploads',
                        'description' => null,
                        'created_at' => $file->created_at,
                        'formatted_size' => $file->formatted_size,
                        'url' => $file->url,
                        'is_system_file' => true,
                        'tags' => [],
                    ];
                });
                break;

            default:
                break;
        }

        return $files;
    }

    /**
     * Get all folders for a band in hierarchical structure
     *
     * @param int $bandId
     * @return array
     */
    /**
     * Get immediate subfolders of a given folder path
     *
     * @param int $bandId
     * @param string|null $parentPath
     * @return array
     */
    public function getSubfoldersOf($bandId, $parentPath = null)
    {
        // Get all folder paths
        $allPaths = MediaFile::where('band_id', $bandId)
            ->whereNotNull('folder_path')
            ->distinct()
            ->pluck('folder_path');

        // Also get defined empty folders
        $definedPaths = \App\Models\MediaFolder::where('band_id', $bandId)
            ->pluck('path');

        $allPaths = $allPaths->merge($definedPaths)->unique();

        // Get Google Drive folder syncs for this band
        $driveSyncedFolders = \App\Models\GoogleDriveFolder::whereHas('connection', function ($query) use ($bandId) {
            $query->where('band_id', $bandId);
        })
        ->get()
        ->keyBy('local_folder_path');

        $subfolders = [];

        foreach ($allPaths as $path) {
            // If viewing root (null), get top-level folders
            if ($parentPath === null || $parentPath === '') {
                // Top-level folders have no slash or only one level deep
                if (!str_contains($path, '/')) {
                    $subfolders[$path] = $path;
                } else {
                    // Get the first part before the first slash
                    $firstLevel = explode('/', $path)[0];
                    $subfolders[$firstLevel] = $firstLevel;
                }
            } else {
                // Check if this path is a direct child of parentPath
                $parentPrefix = rtrim($parentPath, '/') . '/';
                if (str_starts_with($path, $parentPrefix)) {
                    // Get the part after the parent
                    $remainder = substr($path, strlen($parentPrefix));

                    // If no more slashes, this is a direct child
                    if (!str_contains($remainder, '/')) {
                        $subfolders[$path] = $path;
                    } else {
                        // Get the first level of the remainder (immediate subfolder)
                        $immediateChild = $parentPrefix . explode('/', $remainder)[0];
                        $subfolders[$immediateChild] = $immediateChild;
                    }
                }
            }
        }

        // Get file counts for each subfolder
        $result = [];
        foreach (array_unique($subfolders) as $folderPath) {
            $fileCount = MediaFile::where('band_id', $bandId)
                ->where('folder_path', 'LIKE', $folderPath . '%')
                ->count();

            $folderName = basename($folderPath);

            $result[] = [
                'path' => $folderPath,
                'name' => $folderName,
                'file_count' => $fileCount,
                'is_folder' => true,
                'is_drive_synced' => isset($driveSyncedFolders[$folderPath]),
                'drive_folder_name' => $driveSyncedFolders[$folderPath]->google_folder_name ?? null,
            ];
        }

        // Sort by name
        usort($result, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    public function getFolders($bandId)
    {
        // Get folders that have files
        $foldersWithFiles = MediaFile::where('band_id', $bandId)
            ->whereNotNull('folder_path')
            ->select('folder_path')
            ->selectRaw('COUNT(*) as file_count')
            ->groupBy('folder_path')
            ->orderBy('folder_path')
            ->get()
            ->keyBy('folder_path');

        // Get all defined folders (including empty ones)
        $definedFolders = MediaFolder::where('band_id', $bandId)
            ->orderBy('path')
            ->get();

        // Get Google Drive folder syncs for this band
        $driveSyncedFolders = \App\Models\GoogleDriveFolder::whereHas('connection', function ($query) use ($bandId) {
            $query->where('band_id', $bandId);
        })
        ->get()
        ->keyBy('local_folder_path');

        // Merge both lists
        $allFolders = [];

        // Add folders with files
        foreach ($foldersWithFiles as $path => $folder) {
            $allFolders[$path] = [
                'path' => $path,
                'file_count' => $folder->file_count,
                'is_drive_synced' => isset($driveSyncedFolders[$path]),
                'drive_folder_name' => $driveSyncedFolders[$path]->google_folder_name ?? null,
            ];
        }

        // Add defined folders (empty ones will have file_count = 0)
        foreach ($definedFolders as $folder) {
            if (!isset($allFolders[$folder->path])) {
                $allFolders[$folder->path] = [
                    'path' => $folder->path,
                    'file_count' => 0,
                    'is_drive_synced' => isset($driveSyncedFolders[$folder->path]),
                    'drive_folder_name' => $driveSyncedFolders[$folder->path]->google_folder_name ?? null,
                ];
            }
        }

        // Add static system folders (always present, cannot be deleted)
        $systemFolders = $this->getSystemFolderCounts($bandId);
        foreach ($systemFolders as $systemFolder => $count) {
            if (!isset($allFolders[$systemFolder])) {
                $allFolders[$systemFolder] = [
                    'path' => $systemFolder,
                    'file_count' => $count,
                    'is_system' => true,
                    'is_drive_synced' => false,
                    'drive_folder_name' => null,
                ];
            } else {
                // Mark existing folder as system folder and add system file counts
                $allFolders[$systemFolder]['is_system'] = true;
                $allFolders[$systemFolder]['file_count'] += $count;
            }
        }

        // Sort by path
        ksort($allFolders);

        // Build hierarchical tree structure
        return $this->buildFolderTree(array_values($allFolders));
    }

    /**
     * Get file counts for system folders from their respective tables
     *
     * @param int $bandId
     * @return array
     */
    protected function getSystemFolderCounts($bandId)
    {
        $counts = [
            'charts' => 0,
            'contracts' => 0,
            'event_uploads' => 0,
        ];

        // Count chart files
        $counts['charts'] = DB::table('chart_uploads')
            ->join('charts', 'chart_uploads.chart_id', '=', 'charts.id')
            ->where('charts.band_id', $bandId)
            ->count();

        // Count event upload files (event_attachments)
        $counts['event_uploads'] = EventAttachment::whereHas('event', function ($query) use ($bandId) {
            $query->whereHasMorph('eventable', ['App\Models\Bookings', 'App\Models\BandEvents'], function ($q) use ($bandId) {
                $q->where('band_id', $bandId);
            });
        })->count();

        // Media files are already counted in the main query, but count any in 'media' folder
        // This is already handled by the media_files query above


        // Booking contracts (from bookings table)
        $bookingContractsCount = DB::table('bookings')
            ->join('contracts', 'bookings.id', '=', 'contracts.contractable_id')
            ->where('band_id', $bandId)
            ->where('contracts.contractable_type', 'App\\Models\\Bookings')
            ->whereNotNull('asset_url')
            ->count();

        $counts['contracts'] = $bookingContractsCount;

        return $counts;
    }

    /**
     * Build a hierarchical folder tree from flat folder paths
     *
     * @param array $folders
     * @return array
     */
    protected function buildFolderTree($folders)
    {
        $tree = [];

        foreach ($folders as $folder) {
            $parts = explode('/', $folder['path']);
            $current = &$tree;

            $fullPath = '';
            foreach ($parts as $index => $part) {
                $fullPath = $fullPath ? $fullPath . '/' . $part : $part;

                if (!isset($current[$part])) {
                    $current[$part] = [
                        'name' => $part,
                        'path' => $fullPath,
                        'file_count' => 0,
                        'is_system' => false,
                        'is_drive_synced' => false,
                        'drive_folder_name' => null,
                        'children' => []
                    ];
                }

                // Add file count and flags to the deepest folder
                if ($index === count($parts) - 1) {
                    $current[$part]['file_count'] = $folder['file_count'];
                    $current[$part]['is_system'] = $folder['is_system'] ?? false;
                    $current[$part]['is_drive_synced'] = $folder['is_drive_synced'] ?? false;
                    $current[$part]['drive_folder_name'] = $folder['drive_folder_name'] ?? null;
                }

                $current = &$current[$part]['children'];
            }
        }

        return array_values($this->flattenTree($tree));
    }

    /**
     * Flatten tree for easier rendering
     *
     * @param array $tree
     * @param int $depth
     * @return array
     */
    protected function flattenTree($tree, $depth = 0)
    {
        $result = [];

        foreach ($tree as $node) {
            $children = $node['children'];
            unset($node['children']);
            $node['depth'] = $depth;
            $node['has_children'] = count($children) > 0;
            $result[] = $node;

            if (count($children) > 0) {
                $result = array_merge($result, $this->flattenTree($children, $depth + 1));
            }
        }

        return $result;
    }

    /**
     * Create a folder record
     *
     * @param int $bandId
     * @param string $folderPath
     * @param int $userId
     * @return MediaFolder
     */
    public function createFolder($bandId, $folderPath, $userId)
    {
        // Create parent folders if they don't exist
        $parts = explode('/', $folderPath);
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath = $currentPath ? $currentPath . '/' . $part : $part;

            MediaFolder::firstOrCreate(
                [
                    'band_id' => $bandId,
                    'path' => $currentPath,
                ],
                [
                    'created_by' => $userId,
                ]
            );
        }

        return MediaFolder::where('band_id', $bandId)
            ->where('path', $folderPath)
            ->first();
    }

    /**
     * Rename a folder and update all files
     *
     * @param int $bandId
     * @param string $oldPath
     * @param string $newPath
     * @return int
     */
    public function renameFolder($bandId, $oldPath, $newPath)
    {
        // Update files
        $fileCount = MediaFile::where('band_id', $bandId)
            ->where(function ($query) use ($oldPath) {
                $query->where('folder_path', $oldPath)
                      ->orWhere('folder_path', 'LIKE', $oldPath . '/%');
            })
            ->update([
                'folder_path' => DB::raw("REPLACE(folder_path, '" . addslashes($oldPath) . "', '" . addslashes($newPath) . "')")
            ]);

        // Update folder records
        MediaFolder::where('band_id', $bandId)
            ->where(function ($query) use ($oldPath) {
                $query->where('path', $oldPath)
                      ->orWhere('path', 'LIKE', $oldPath . '/%');
            })
            ->update([
                'path' => DB::raw("REPLACE(path, '" . addslashes($oldPath) . "', '" . addslashes($newPath) . "')")
            ]);

        return $fileCount;
    }

    /**
     * Delete a folder and move files to root
     *
     * @param int $bandId
     * @param string $folderPath
     * @return int
     */
    public function deleteFolder($bandId, $folderPath)
    {
        // Move files to root
        $fileCount = MediaFile::where('band_id', $bandId)
            ->where(function ($query) use ($folderPath) {
                $query->where('folder_path', $folderPath)
                      ->orWhere('folder_path', 'LIKE', $folderPath . '/%');
            })
            ->update(['folder_path' => null]);

        // Delete folder records
        MediaFolder::where('band_id', $bandId)
            ->where(function ($query) use ($folderPath) {
                $query->where('path', $folderPath)
                      ->orWhere('path', 'LIKE', $folderPath . '/%');
            })
            ->delete();

        return $fileCount;
    }

    /**
     * Move multiple files to a folder
     *
     * @param int $bandId
     * @param array $mediaIds
     * @param string|null $folderPath
     * @return int
     */
    public function bulkMove($bandId, array $mediaIds, $folderPath = null)
    {
        // Create folder record if moving to a folder
        if (!empty($folderPath)) {
            // Get any user ID from the media files being moved
            $userId = MediaFile::whereIn('id', $mediaIds)->where('band_id', $bandId)->value('user_id');
            $this->createFolder($bandId, $folderPath, $userId);
        }

        return MediaFile::whereIn('id', $mediaIds)
            ->where('band_id', $bandId)
            ->update(['folder_path' => $folderPath]);
    }

    /**
     * Update media file details
     *
     * @param MediaFile $media
     * @param array $data
     * @return MediaFile
     */
    public function updateMedia(MediaFile $media, array $data)
    {
        // Create folder record if updating folder path
        if (isset($data['folder_path']) && !empty($data['folder_path'])) {
            $this->createFolder($media->band_id, $data['folder_path'], $media->user_id);
        }

        $media->update($data);

        if (isset($data['tags'])) {
            $media->tags()->sync($data['tags']);
        }

        return $media->fresh(['tags', 'uploader']);
    }

    /**
     * Create associations for media file
     *
     * @param MediaFile $media
     * @param int|null $bookingId
     * @param int|null $eventId
     * @return void
     */
    public function createAssociations(MediaFile $media, $bookingId = null, $eventId = null)
    {
        if ($bookingId) {
            $media->associations()->create([
                'associable_type' => 'App\\Models\\Bookings',
                'associable_id' => $bookingId,
            ]);
        }

        if ($eventId) {
            $media->associations()->create([
                'associable_type' => 'App\\Models\\Events',
                'associable_id' => $eventId,
            ]);
        }
    }
}
