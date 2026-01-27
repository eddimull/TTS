<?php

namespace App\Http\Controllers;

use App\Models\ChunkedUpload;
use App\Models\MediaFile;
use App\Services\MediaLibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadController extends Controller
{
    /**
     * Initiate a chunked upload session.
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'filesize' => 'required|integer|max:5368709120', // 5GB max
            'mime_type' => 'required|string',
            'total_chunks' => 'required|integer|min:1',
            'folder_path' => 'nullable|string|max:255',
            'event_id' => 'nullable|integer|exists:events,id',
        ]);

        $uploadId = Str::uuid();

        // If event_id not provided but folder_path is, try to find event from folder path
        $eventId = $validated['event_id'] ?? null;
        if (!$eventId && !empty($validated['folder_path'])) {
            $mediaService = app(MediaLibraryService::class);
            $eventId = $mediaService->getEventIdFromFolderPath($validated['folder_path']);
        }

        $upload = ChunkedUpload::create([
            'upload_id' => $uploadId,
            'filename' => $validated['filename'],
            'filesize' => $validated['filesize'],
            'mime_type' => $validated['mime_type'],
            'folder_path' => $validated['folder_path'] ?? null,
            'event_id' => $eventId,
            'total_chunks' => $validated['total_chunks'],
            'user_id' => auth()->id(),
            'status' => 'initiated',
        ]);

        return response()->json([
            'upload_id' => $uploadId,
        ]);
    }

    /**
     * Upload a single chunk.
     */
    public function uploadChunk(Request $request, $uploadId)
    {
        $validated = $request->validate([
            'chunk' => 'required|file',
            'chunk_index' => 'required|integer|min:0',
        ]);

        $upload = ChunkedUpload::where('upload_id', $uploadId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Validate chunk index is within range
        if ($validated['chunk_index'] >= $upload->total_chunks) {
            return response()->json([
                'error' => 'Invalid chunk index'
            ], 400);
        }

        // Store chunk temporarily
        $chunkPath = "chunks/{$uploadId}/{$validated['chunk_index']}";
        Storage::disk('local')->put(
            $chunkPath,
            file_get_contents($request->file('chunk')->getRealPath())
        );

        // Update progress
        $upload->increment('chunks_uploaded');
        $upload->update([
            'status' => 'uploading',
            'last_chunk_at' => now()
        ]);

        $progress = ($upload->fresh()->chunks_uploaded / $upload->total_chunks) * 100;

        return response()->json([
            'success' => true,
            'progress' => $progress,
            'chunks_uploaded' => $upload->fresh()->chunks_uploaded,
        ]);
    }

    /**
     * Get the status of a chunked upload.
     */
    public function getStatus($uploadId)
    {
        $upload = ChunkedUpload::where('upload_id', $uploadId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'upload_id' => $upload->upload_id,
            'filename' => $upload->filename,
            'filesize' => $upload->filesize,
            'mime_type' => $upload->mime_type,
            'total_chunks' => $upload->total_chunks,
            'chunks_uploaded' => $upload->chunks_uploaded,
            'status' => $upload->status,
            'progress' => ($upload->chunks_uploaded / $upload->total_chunks) * 100,
        ]);
    }

    /**
     * Complete the upload by merging all chunks.
     */
    public function complete(Request $request, $uploadId)
    {
        $upload = ChunkedUpload::where('upload_id', $uploadId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Verify all chunks received
        if ($upload->chunks_uploaded !== $upload->total_chunks) {
            return response()->json([
                'error' => 'Missing chunks',
                'expected' => $upload->total_chunks,
                'received' => $upload->chunks_uploaded,
            ], 400);
        }

        try {
            // Generate temporary filename
            $tempFilename = $upload->upload_id . '_' . $upload->filename;
            $tempPath = "temp/{$tempFilename}";

            // Merge chunks STREAMING (not loading all into memory)
            $disk = Storage::disk('local');
            $tempFullPath = $disk->path($tempPath);

            // Open output file for writing
            $output = fopen($tempFullPath, 'wb');

            if ($output === false) {
                throw new \Exception("Could not create temporary file");
            }

            \Log::info('Starting chunk merge', [
                'upload_id' => $uploadId,
                'total_chunks' => $upload->total_chunks
            ]);

            // Stream each chunk directly to the output file
            for ($i = 0; $i < $upload->total_chunks; $i++) {
                $chunkPath = "chunks/{$uploadId}/{$i}";

                if (!$disk->exists($chunkPath)) {
                    fclose($output);
                    throw new \Exception("Chunk {$i} is missing");
                }

                // Stream chunk content directly to output file
                $chunkFullPath = $disk->path($chunkPath);
                $input = fopen($chunkFullPath, 'rb');

                if ($input === false) {
                    fclose($output);
                    throw new \Exception("Could not read chunk {$i}");
                }

                // Copy in 1MB blocks to avoid memory issues
                while (!feof($input)) {
                    $buffer = fread($input, 1024 * 1024); // 1MB at a time
                    fwrite($output, $buffer);
                }

                fclose($input);

                // Log progress every 10 chunks
                if (($i + 1) % 10 === 0 || $i === $upload->total_chunks - 1) {
                    \Log::info("Merged chunks: " . ($i + 1) . "/" . $upload->total_chunks);
                }
            }

            fclose($output);

            \Log::info('Chunk merge complete', ['upload_id' => $uploadId]);

            // Get the user's band
            $band = auth()->user()->bands()->first();

            if (!$band) {
                throw new \Exception('User must belong to a band to upload media');
            }

            \Log::info('Band details for upload', [
                'band_id' => $band->id,
                'band_name' => $band->name ?? 'N/A',
                'site_name' => $band->site_name ?? 'NULL',
                'site_name_type' => gettype($band->site_name)
            ]);

            // Generate UUID filename for S3 storage
            $extension = pathinfo($upload->filename, PATHINFO_EXTENSION);
            $uuid = \Str::uuid();
            $filename = $uuid . '.' . $extension;

            // Store with band-scoped path directly to S3
            $storagePath = $band->site_name . '/media';
            $storageDisk = config('filesystems.default'); // 's3' or whatever is configured
            $fullStoragePath = $storagePath . '/' . $filename;

            \Log::info('Preparing to upload to S3', [
                'band_site_name' => $band->site_name,
                'uuid' => $uuid,
                'extension' => $extension,
                'filename' => $filename,
                'storage_path' => $storagePath,
                'full_storage_path' => $fullStoragePath,
                'storage_disk' => $storageDisk,
                'temp_path' => $tempPath
            ]);

            // Copy merged file from local to S3 (using stream to avoid memory issues)
            $localFilePath = $disk->path($tempPath);
            $s3Disk = Storage::disk($storageDisk);

            \Log::info('Starting S3 upload', [
                'path' => $fullStoragePath,
                'size' => filesize($localFilePath)
            ]);

            // Use putFileAs with a stream to avoid loading entire file into memory
            $stream = fopen($localFilePath, 'rb');
            $uploadResult = $s3Disk->put(
                $fullStoragePath,
                $stream,
                'private'
            );

            if (is_resource($stream)) {
                fclose($stream);
            }

            \Log::info('S3 upload complete', [
                'result' => $uploadResult,
                'path' => $fullStoragePath
            ]);

            // Determine media type
            $mimeType = $upload->mime_type;
            $mediaType = $this->determineMediaType($mimeType);

            $mediaData = [
                'band_id' => $band->id,
                'user_id' => auth()->id(),
                'filename' => $upload->filename,
                'stored_filename' => $fullStoragePath,
                'mime_type' => $mimeType,
                'file_size' => $upload->filesize,
                'disk' => $storageDisk,
                'media_type' => $mediaType,
                'title' => $upload->filename,
                'description' => null,
                'folder_path' => $upload->folder_path,
            ];

            \Log::info('Creating MediaFile record', [
                'data' => $mediaData,
                'full_storage_path_type' => gettype($fullStoragePath),
                'full_storage_path_value' => $fullStoragePath
            ]);

            // Create MediaFile record
            $mediaFile = MediaFile::create($mediaData);

            // Update quota
            $quota = $band->storageQuota;
            if ($quota) {
                $quota->quota_used += $upload->filesize;
                $quota->save();
            }

            // Generate thumbnail for images and videos
            $mediaLibraryService = app(MediaLibraryService::class);
            if ($mediaType === 'image' || $mediaType === 'video') {
                try {
                    $mediaLibraryService->generateThumbnail($mediaFile);
                } catch (\Exception $e) {
                    \Log::error('Thumbnail generation failed', [
                        'media_file_id' => $mediaFile->id,
                        'error' => $e->getMessage()
                    ]);
                    // Don't fail the upload if thumbnail generation fails
                }
            }

            // Queue notification for event folder uploads
            if ($upload->event_id) {
                $mediaLibraryService->queueEventMediaNotification($upload->event_id);
            }

            // Clean up chunks
            $disk->deleteDirectory("chunks/{$uploadId}");

            // Clean up temp file
            $disk->delete($tempPath);

            // Update upload record
            $upload->update([
                'status' => 'completed',
                'media_id' => $mediaFile->id,
            ]);

            return response()->json([
                'success' => true,
                'media' => $mediaFile,
            ]);

        } catch (\Exception $e) {
            \Log::error('Chunked upload completion failed', [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $upload->update(['status' => 'failed']);

            return response()->json([
                'error' => 'Upload completion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determine media type from MIME type
     */
    private function determineMediaType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        return 'document';
    }
}
