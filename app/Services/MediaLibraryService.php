<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\BandStorageQuota;
use App\Models\EventAttachment;
use App\Models\Events;
use App\Models\Contacts;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

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

        // Auto-populate folder_path from event if provided
        if (!empty($additionalData['event_id']) && empty($additionalData['folder_path'])) {
            $event = Events::find($additionalData['event_id']);
            if ($event && $event->media_folder_path) {
                $additionalData['folder_path'] = $event->media_folder_path;
            }
        }

        // Auto-detect event_id from folder_path if not explicitly provided
        if (empty($additionalData['event_id']) && !empty($additionalData['folder_path'])) {
            $additionalData['event_id'] = $this->getEventIdFromFolderPath($additionalData['folder_path']);
        }

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

        // Create associations if event_id or booking_id provided
        if (!empty($additionalData['event_id']) || !empty($additionalData['booking_id'])) {
            $this->createAssociations(
                $mediaFile,
                $additionalData['booking_id'] ?? null,
                $additionalData['event_id'] ?? null
            );
        }

        // Generate thumbnail for images and videos
        if ($mediaType === 'image' || $mediaType === 'video') {
            $this->generateThumbnail($mediaFile);
        }

        // Update quota
        $quota->quota_used += $file->getSize();
        $quota->save();

        // Clear folder caches after upload
        $this->clearMediaCaches($band->id);

        // Queue notification for event folder uploads
        if (!empty($additionalData['event_id'])) {
            $this->queueEventMediaNotification($additionalData['event_id']);
        }

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
        $maxSize = 1000 * 1024 * 1024; // 1000MB
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds maximum allowed (1GB)');
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
    public function generateThumbnail($mediaFile)
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

            if ($mediaFile->media_type === 'video') {
                // Use FFmpeg for video thumbnails
                $this->generateVideoThumbnail($mediaFile, $thumbnailPath);
            } else if ($mediaFile->media_type === 'image') {
                // Use ImageMagick for image thumbnails
                $this->generateImageThumbnail($mediaFile, $thumbnailPath);
            }

            // Log success (note: we don't verify thumbnail exists to avoid S3 timing issues)
            \Log::info('Thumbnail generated successfully', [
                'media_file_id' => $mediaFile->id,
                'thumbnail_path' => $thumbnailPath
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
        $disk = Storage::disk($mediaFile->disk);
        $tempVideoPath = null;
        $tempThumbPath = null;

        try {
            // For S3/remote storage, download to temp location first
            if ($mediaFile->disk !== 'local') {
                $tempVideoPath = sys_get_temp_dir() . '/' . uniqid('video_') . '.' . pathinfo($mediaFile->stored_filename, PATHINFO_EXTENSION);
                $tempThumbPath = sys_get_temp_dir() . '/' . uniqid('thumb_') . '.jpg';

                \Log::info('Downloading video from S3 for thumbnail generation', [
                    'source' => $mediaFile->stored_filename,
                    'temp_path' => $tempVideoPath,
                    'expected_size' => $mediaFile->file_size
                ]);

                // Download video from S3 to temp location using stream for large files
                // Retry a few times in case S3 needs time to make the file available
                $stream = null;
                $maxAttempts = 3;
                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    $stream = $disk->readStream($mediaFile->stored_filename);
                    if ($stream !== false && $stream !== null) {
                        break;
                    }

                    if ($attempt < $maxAttempts) {
                        \Log::info('Stream open failed, retrying...', [
                            'attempt' => $attempt,
                            'max_attempts' => $maxAttempts
                        ]);
                        sleep(2); // Wait 2 seconds before retry
                    }
                }

                if ($stream === false || $stream === null) {
                    throw new \Exception("Failed to open stream from S3 after {$maxAttempts} attempts: {$mediaFile->stored_filename}");
                }

                $localStream = fopen($tempVideoPath, 'w');
                if ($localStream === false) {
                    fclose($stream);
                    throw new \Exception("Failed to open local file for writing: {$tempVideoPath}");
                }

                $bytesWritten = stream_copy_to_stream($stream, $localStream);
                fclose($stream);
                fclose($localStream);

                // Verify download
                if (!file_exists($tempVideoPath)) {
                    throw new \Exception("Failed to create temp video file at: {$tempVideoPath}");
                }

                $fileSize = filesize($tempVideoPath);
                \Log::info('Video downloaded to temp location', [
                    'temp_path' => $tempVideoPath,
                    'bytes_written' => $bytesWritten,
                    'file_size' => $fileSize,
                    'expected_size' => $mediaFile->file_size
                ]);

                if ($fileSize === 0) {
                    throw new \Exception("Downloaded video file is empty");
                }

                $sourceVideoPath = $tempVideoPath;
                $outputThumbPath = $tempThumbPath;
            } else {
                // Local storage - use direct paths
                $sourceVideoPath = $disk->path($mediaFile->stored_filename);
                $outputThumbPath = $disk->path($thumbnailPath);
            }

            \Log::info('Attempting to open video with FFmpeg', [
                'path' => $sourceVideoPath,
                'exists' => file_exists($sourceVideoPath),
                'readable' => is_readable($sourceVideoPath)
            ]);

            // Generate thumbnail using FFmpeg
            $ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
                'ffprobe.binaries' => '/usr/bin/ffprobe',
                'timeout'          => 3600,
                'ffmpeg.threads'   => 12,
            ]);

            $video = $ffmpeg->open($sourceVideoPath);
            $frame = $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(1));
            $frame->save($outputThumbPath);

            \Log::info('FFmpeg thumbnail generated', [
                'output_path' => $outputThumbPath,
                'exists' => file_exists($outputThumbPath)
            ]);

            // For S3/remote storage, upload thumbnail back
            if ($mediaFile->disk !== 'local') {
                if (!file_exists($tempThumbPath)) {
                    throw new \Exception("Thumbnail was not created at: {$tempThumbPath}");
                }
                $disk->put($thumbnailPath, file_get_contents($tempThumbPath));
                \Log::info('Thumbnail uploaded to S3', ['path' => $thumbnailPath]);
            }
        } finally {
            // Clean up temp files
            if ($tempVideoPath && file_exists($tempVideoPath)) {
                unlink($tempVideoPath);
            }
            if ($tempThumbPath && file_exists($tempThumbPath)) {
                unlink($tempThumbPath);
            }
        }
    }

    /**
     * Generate thumbnail for an image using Imagick
     *
     * @param \App\Models\MediaFile $mediaFile
     * @param string $thumbnailPath
     */
    private function generateImageThumbnail($mediaFile, $thumbnailPath)
    {
        $disk = Storage::disk($mediaFile->disk);
        $tempImagePath = null;
        $tempThumbPath = null;

        try {
            // For S3/remote storage, download to temp location first
            if ($mediaFile->disk !== 'local') {
                $tempImagePath = sys_get_temp_dir() . '/' . uniqid('image_') . '.' . pathinfo($mediaFile->stored_filename, PATHINFO_EXTENSION);
                $tempThumbPath = sys_get_temp_dir() . '/' . uniqid('thumb_') . '.jpg';

                // Download image from S3 to temp location using stream for large files
                // Retry a few times in case S3 needs time to make the file available
                $stream = null;
                $maxAttempts = 3;
                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    $stream = $disk->readStream($mediaFile->stored_filename);
                    if ($stream !== false && $stream !== null) {
                        break;
                    }

                    if ($attempt < $maxAttempts) {
                        \Log::info('Stream open failed for image, retrying...', [
                            'attempt' => $attempt,
                            'max_attempts' => $maxAttempts
                        ]);
                        sleep(2); // Wait 2 seconds before retry
                    }
                }

                if ($stream === false || $stream === null) {
                    throw new \Exception("Failed to open stream from S3 after {$maxAttempts} attempts: {$mediaFile->stored_filename}");
                }

                $localStream = fopen($tempImagePath, 'w');
                if ($localStream === false) {
                    fclose($stream);
                    throw new \Exception("Failed to open local file for writing: {$tempImagePath}");
                }

                stream_copy_to_stream($stream, $localStream);
                fclose($stream);
                fclose($localStream);

                $sourceImagePath = $tempImagePath;
                $outputThumbPath = $tempThumbPath;
            } else {
                // Local storage - use direct paths
                $sourceImagePath = $disk->path($mediaFile->stored_filename);
                $outputThumbPath = $disk->path($thumbnailPath);
            }

            // Generate thumbnail using Imagick
            $imagick = new \Imagick($sourceImagePath);

            // Resize to max 400x400 maintaining aspect ratio
            $imagick->thumbnailImage(400, 400, true);

            // Set JPEG quality
            $imagick->setImageCompressionQuality(85);
            $imagick->setImageFormat('jpeg');

            // Save the thumbnail
            $imagick->writeImage($outputThumbPath);
            $imagick->clear();
            $imagick->destroy();

            // For S3/remote storage, upload thumbnail back
            if ($mediaFile->disk !== 'local') {
                $disk->put($thumbnailPath, file_get_contents($tempThumbPath));
            }
        } finally {
            // Clean up temp files
            if ($tempImagePath && file_exists($tempImagePath)) {
                unlink($tempImagePath);
            }
            if ($tempThumbPath && file_exists($tempThumbPath)) {
                unlink($tempThumbPath);
            }
        }
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
        // Cache subfolder data for 5 minutes
        $cacheKey = "subfolders_{$bandId}_" . ($parentPath ?: 'root');

        return \Cache::remember($cacheKey, 300, function () use ($bandId, $parentPath) {
            // Special handling for Event Media virtual folder
            if ($parentPath === 'event_media') {
                // Get all event folder paths for this band
                $eventFolderPaths = Events::whereNotNull('media_folder_path')
                    ->where(function ($query) use ($bandId) {
                        // Events from Bookings
                        $query->where('eventable_type', 'App\\Models\\Bookings')
                            ->whereIn('eventable_id', function ($subQuery) use ($bandId) {
                                $subQuery->select('id')
                                    ->from('bookings')
                                    ->where('band_id', $bandId);
                            })
                            // Events from BandEvents
                            ->orWhere(function ($q) use ($bandId) {
                                $q->where('eventable_type', 'App\\Models\\BandEvents')
                                    ->whereIn('eventable_id', function ($subQuery) use ($bandId) {
                                        $subQuery->select('id')
                                            ->from('band_events')
                                            ->where('band_id', $bandId);
                                    });
                            });
                    })
                    ->pluck('media_folder_path')
                    ->unique();

                // Extract just the top-level folders (years) from event paths
                $subfolders = [];
                foreach ($eventFolderPaths as $path) {
                    if (empty($path)) continue;

                    // Event paths are like "2026/01/event-name"
                    // We want to show just the year level: "2026"
                    if (str_contains($path, '/')) {
                        $firstLevel = explode('/', $path)[0];
                        $subfolders[$firstLevel] = $firstLevel;
                    }
                }

                // Build result with file counts for each year folder
                $result = [];
                foreach (array_unique($subfolders) as $folderPath) {
                    // Count all files in this year's event folders
                    $fileCount = MediaFile::where('band_id', $bandId)
                        ->where('folder_path', 'LIKE', $folderPath . '/%')
                        ->count();

                    $result[] = [
                        'path' => $folderPath,
                        'name' => $folderPath,
                        'file_count' => $fileCount,
                        'is_folder' => true,
                        'is_drive_synced' => false,
                        'drive_folder_name' => null,
                        'is_event_folder' => true,
                    ];
                }

                // Sort by name (year) descending so newest first
                usort($result, fn($a, $b) => strcmp($b['name'], $a['name']));

                return $result;
            }

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

        // Get file counts for all subfolders in a single query
        $uniqueSubfolders = array_unique($subfolders);

        // Build a single query with OR conditions for all folders
        $fileCounts = [];
        if (!empty($uniqueSubfolders)) {
            $query = MediaFile::where('band_id', $bandId)
                ->select('folder_path', DB::raw('count(*) as count'))
                ->groupBy('folder_path');

            // Add conditions to match each subfolder and its children
            $query->where(function($q) use ($uniqueSubfolders) {
                foreach ($uniqueSubfolders as $folderPath) {
                    $q->orWhere('folder_path', 'LIKE', $folderPath . '%');
                }
            });

            $counts = $query->get()->keyBy('folder_path');

            // Sum up counts for each folder (including children)
            foreach ($uniqueSubfolders as $folderPath) {
                $total = 0;
                foreach ($counts as $path => $item) {
                    if (str_starts_with($path, $folderPath)) {
                        $total += $item->count;
                    }
                }
                $fileCounts[$folderPath] = $total;
            }
        }

        // Build result array
        $result = [];
        foreach ($uniqueSubfolders as $folderPath) {
            $folderName = basename($folderPath);

            $result[] = [
                'path' => $folderPath,
                'name' => $folderName,
                'file_count' => $fileCounts[$folderPath] ?? 0,
                'is_folder' => true,
                'is_drive_synced' => isset($driveSyncedFolders[$folderPath]),
                'drive_folder_name' => $driveSyncedFolders[$folderPath]->google_folder_name ?? null,
            ];
        }

            // Sort by name
            usort($result, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            return $result;
        });
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

        // Get event folder paths for this band
        // Query events that belong to bookings or band_events for this band
        $eventFolderPaths = Events::whereNotNull('media_folder_path')
            ->where(function ($query) use ($bandId) {
                // Events from Bookings
                $query->where('eventable_type', 'App\\Models\\Bookings')
                    ->whereIn('eventable_id', function ($subQuery) use ($bandId) {
                        $subQuery->select('id')
                            ->from('bookings')
                            ->where('band_id', $bandId);
                    })
                    // Events from BandEvents
                    ->orWhere(function ($q) use ($bandId) {
                        $q->where('eventable_type', 'App\\Models\\BandEvents')
                            ->whereIn('eventable_id', function ($subQuery) use ($bandId) {
                                $subQuery->select('id')
                                    ->from('band_events')
                                    ->where('band_id', $bandId);
                            });
                    });
            })
            ->pluck('media_folder_path')
            ->toArray();

        // Merge both lists
        $allFolders = [];

        // Add folders with files
        foreach ($foldersWithFiles as $path => $folder) {
            $allFolders[$path] = [
                'path' => $path,
                'file_count' => $folder->file_count,
                'is_drive_synced' => isset($driveSyncedFolders[$path]),
                'drive_folder_name' => $driveSyncedFolders[$path]->google_folder_name ?? null,
                'is_event_folder' => in_array($path, $eventFolderPaths),
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
                    'is_event_folder' => in_array($folder->path, $eventFolderPaths),
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
                    'is_event_folder' => $systemFolder === 'event_media',
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
        $tree = $this->buildFolderTree(array_values($allFolders));

        // Mark event_media as having children if there are any event folders
        $hasEventFolders = !empty($eventFolderPaths);
        foreach ($tree as &$folder) {
            if ($folder['path'] === 'event_media' && $hasEventFolders) {
                $folder['has_children'] = true;
            }
        }
        unset($folder); // Break reference

        return $tree;
    }

    /**
     * Get file counts for system folders from their respective tables
     *
     * @param int $bandId
     * @return array
     */
    protected function getSystemFolderCounts($bandId)
    {
        // Cache system folder counts for 10 minutes to reduce query load
        return \Cache::remember("system_folder_counts_{$bandId}", 600, function () use ($bandId) {
            $counts = [
                'charts' => 0,
                'contracts' => 0,
                'event_uploads' => 0,
                'event_media' => 0,
            ];

            // Count chart files
            $counts['charts'] = DB::table('chart_uploads')
                ->join('charts', 'chart_uploads.chart_id', '=', 'charts.id')
                ->where('charts.band_id', $bandId)
                ->count();

            // Count event upload files (event_attachments)
            $counts['event_uploads'] = EventAttachment::whereIn('event_id', function ($query) use ($bandId) {
                $query->select('id')
                    ->from('events')
                    ->where(function ($q) use ($bandId) {
                        // Events from Bookings
                        $q->where('eventable_type', 'App\\Models\\Bookings')
                            ->whereIn('eventable_id', function ($subQuery) use ($bandId) {
                                $subQuery->select('id')
                                    ->from('bookings')
                                    ->where('band_id', $bandId);
                            })
                            // Events from BandEvents
                            ->orWhere(function ($sq) use ($bandId) {
                                $sq->where('eventable_type', 'App\\Models\\BandEvents')
                                    ->whereIn('eventable_id', function ($subQuery) use ($bandId) {
                                        $subQuery->select('id')
                                            ->from('band_events')
                                            ->where('band_id', $bandId);
                                    });
                            });
                    });
            })->count();

            // Booking contracts (from bookings table)
            $bookingContractsCount = DB::table('bookings')
                ->join('contracts', 'bookings.id', '=', 'contracts.contractable_id')
                ->where('band_id', $bandId)
                ->where('contracts.contractable_type', 'App\\Models\\Bookings')
                ->whereNotNull('asset_url')
                ->count();

            $counts['contracts'] = $bookingContractsCount;

            // Count event media files (files in event-specific folders)
            $counts['event_media'] = MediaFile::where('band_id', $bandId)
                ->whereNotNull('folder_path')
                ->whereIn('folder_path', function ($query) use ($bandId) {
                    $query->select('media_folder_path')
                        ->from('events')
                        ->whereNotNull('media_folder_path')
                        ->where(function ($q) use ($bandId) {
                            // Events from Bookings
                            $q->where('eventable_type', 'App\\Models\\Bookings')
                                ->whereIn('eventable_id', function ($subQuery) use ($bandId) {
                                    $subQuery->select('id')
                                        ->from('bookings')
                                        ->where('band_id', $bandId);
                                })
                                // Events from BandEvents
                                ->orWhere(function ($sq) use ($bandId) {
                                    $sq->where('eventable_type', 'App\\Models\\BandEvents')
                                        ->whereIn('eventable_id', function ($subQuery) use ($bandId) {
                                            $subQuery->select('id')
                                                ->from('band_events')
                                                ->where('band_id', $bandId);
                                        });
                                });
                        });
                })
                ->count();

            return $counts;
        });
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
                        'is_event_folder' => false,
                        'children' => []
                    ];
                }

                // Add file count and flags to the deepest folder
                if ($index === count($parts) - 1) {
                    $current[$part]['file_count'] = $folder['file_count'];
                    $current[$part]['is_system'] = $folder['is_system'] ?? false;
                    $current[$part]['is_drive_synced'] = $folder['is_drive_synced'] ?? false;
                    $current[$part]['drive_folder_name'] = $folder['drive_folder_name'] ?? null;
                    $current[$part]['is_event_folder'] = $folder['is_event_folder'] ?? false;
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

        // Clear folder caches
        $this->clearMediaCaches($bandId);

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

        $updated = MediaFile::whereIn('id', $mediaIds)
            ->where('band_id', $bandId)
            ->update(['folder_path' => $folderPath]);

        // Clear folder caches
        $this->clearMediaCaches($bandId);

        return $updated;
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

    /**
     * Clear media-related caches for a band
     *
     * @param int $bandId
     * @return void
     */
    protected function clearMediaCaches($bandId)
    {
        // Clear folder list cache
        \Cache::forget("media_folders_{$bandId}");

        // Clear system folder counts
        \Cache::forget("system_folder_counts_{$bandId}");

        // Clear all subfolder caches (pattern-based)
        $patterns = ["subfolders_{$bandId}_root"];

        // Try to clear some common subfolder paths
        // Note: This won't catch all possibilities, but covers most common cases
        foreach ($patterns as $pattern) {
            \Cache::forget($pattern);
        }
    }

    /**
     * Create a media folder for an event
     * Generates folder path: {year}/{month}/{event-slug}
     *
     * @param Events $event
     * @return string The folder path that was created
     */
    public function createEventFolder(Events $event): string
    {
        // Generate folder path
        $year = $event->date->format('Y');
        $month = $event->date->format('m');

        // Sanitize event name
        $eventName = $this->sanitizeEventName($event);
        $slug = Str::slug($eventName);

        // Handle duplicate slugs by checking existing folders
        $basePath = "{$year}/{$month}";
        $folderPath = "{$basePath}/{$slug}";
        $counter = 1;

        $bandId = $event->eventable->band_id;

        while (MediaFolder::where('band_id', $bandId)->where('path', $folderPath)->exists()) {
            $folderPath = "{$basePath}/{$slug}-{$counter}";
            $counter++;
        }

        // Create the folder using existing method
        $this->createFolder($bandId, $folderPath, $event->eventable->author_id ?? 1);

        return $folderPath;
    }

    /**
     * Get all media files for a specific event
     *
     * @param Events $event
     * @return Collection
     */
    public function getEventMedia(Events $event): Collection
    {
        $bandId = $event->eventable->band_id;

        // Get media by folder_path or by association
        return MediaFile::where('band_id', $bandId)
            ->where(function ($query) use ($event) {
                // Match by folder path if set
                if ($event->media_folder_path) {
                    $query->where('folder_path', $event->media_folder_path)
                          ->orWhere('folder_path', 'LIKE', $event->media_folder_path . '/%');
                }

                // Or match by media association
                $query->orWhereHas('associations', function ($assocQuery) use ($event) {
                    $assocQuery->where('associable_type', 'App\\Models\\Events')
                               ->where('associable_id', $event->id);
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all media accessible to a contact based on their bookings
     *
     * @param Contacts $contact
     * @return Collection
     */
    public function getContactAccessibleMedia(Contacts $contact): Collection
    {
        // Get all bookings for this contact
        $bookings = $contact->bookings;

        if ($bookings->isEmpty()) {
            return collect([]);
        }

        $bandId = $contact->band_id;
        $bookingIds = $bookings->pluck('id')->toArray();

        // Get all events from these bookings
        $events = Events::where('eventable_type', 'App\\Models\\Bookings')
            ->whereIn('eventable_id', $bookingIds)
            ->where('enable_portal_media_access', true)
            ->get();

        $eventIds = $events->pluck('id')->toArray();
        $folderPaths = $events->whereNotNull('media_folder_path')
            ->pluck('media_folder_path')
            ->toArray();

        // Get media files that are either:
        // 1. In folders belonging to these events
        // 2. Associated with these events
        // 3. Associated with the bookings directly
        return MediaFile::where('band_id', $bandId)
            ->where(function ($query) use ($folderPaths, $eventIds, $bookingIds) {
                // Match by folder paths
                if (!empty($folderPaths)) {
                    $query->where(function ($folderQuery) use ($folderPaths) {
                        foreach ($folderPaths as $path) {
                            $folderQuery->orWhere('folder_path', $path)
                                        ->orWhere('folder_path', 'LIKE', $path . '/%');
                        }
                    });
                }

                // Match by associations
                $query->orWhereHas('associations', function ($assocQuery) use ($eventIds, $bookingIds) {
                    $assocQuery->where(function ($q) use ($eventIds) {
                        $q->where('associable_type', 'App\\Models\\Events')
                          ->whereIn('associable_id', $eventIds);
                    })->orWhere(function ($q) use ($bookingIds) {
                        $q->where('associable_type', 'App\\Models\\Bookings')
                          ->whereIn('associable_id', $bookingIds);
                    });
                });
            })
            ->with(['associations'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Sanitize event name for folder naming
     *
     * @param Events $event
     * @return string
     */
    protected function sanitizeEventName(Events $event): string
    {
        // Use event title if available
        if (!empty($event->title)) {
            return Str::limit($event->title, 50, '');
        }

        // Fallback to event ID
        return "Event-{$event->id}";
    }

    /**
     * Find event ID from a folder path if it's an event media folder.
     *
     * @param string $folderPath
     * @return int|null
     */
    public function getEventIdFromFolderPath(string $folderPath): ?int
    {
        if (empty($folderPath)) {
            return null;
        }

        // Check if this folder path matches any event's media_folder_path
        $event = Events::where('media_folder_path', $folderPath)->first();

        return $event?->id;
    }

    /**
     * Queue notification for event media upload with batching
     *
     * @param int $eventId
     * @return void
     */
    public function queueEventMediaNotification(int $eventId): void
    {
        // Store timestamp in cache to track latest upload
        $cacheKey = "event_media_upload_notification:{$eventId}";
        $timestamp = time();

        // Update cache with new timestamp (overwrites previous)
        // Cache for longer than notification delay to ensure job can read it
        $delayMinutes = config('services.media.upload_notification_delay', 5);
        \Illuminate\Support\Facades\Cache::put($cacheKey, $timestamp, now()->addMinutes($delayMinutes + 10));

        // Dispatch job with current timestamp
        \App\Jobs\NotifyContactsOfMediaUpload::dispatch($eventId, $timestamp);

        \Illuminate\Support\Facades\Log::info("Queued media upload notification for event {$eventId}", [
            'timestamp' => $timestamp,
            'delay_minutes' => $delayMinutes,
        ]);
    }
}
