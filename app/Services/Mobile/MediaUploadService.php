<?php

namespace App\Services\Mobile;

use App\Models\Bands;
use App\Models\BandStorageQuota;
use App\Models\ChunkedUpload;
use App\Models\Events;
use App\Models\MediaFile;
use App\Services\MediaLibraryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    public function __construct(private readonly MediaLibraryService $mediaService) {}

    /**
     * Assemble all chunks, upload the merged file to S3, create the MediaFile
     * record, update the quota, create associations, and clean up temp files.
     *
     * @throws \Exception on any failure
     */
    public function complete(ChunkedUpload $upload, Bands $band): MediaFile
    {
        $tempFilename = $upload->upload_id . '_' . $upload->filename;
        $tempPath     = "temp/{$tempFilename}";
        $disk         = Storage::disk('local');

        if (!$disk->exists('temp')) {
            $disk->makeDirectory('temp');
        }

        $tempFullPath = $disk->path($tempPath);
        $output       = fopen($tempFullPath, 'wb');

        if ($output === false) {
            throw new \Exception('Could not create temporary file.');
        }

        for ($i = 0; $i < $upload->total_chunks; $i++) {
            $chunkPath = "chunks/{$upload->upload_id}/{$i}";

            if (!$disk->exists($chunkPath)) {
                fclose($output);
                throw new \Exception("Chunk {$i} is missing.");
            }

            $input = fopen($disk->path($chunkPath), 'rb');
            while (!feof($input)) {
                fwrite($output, fread($input, 1024 * 1024));
            }
            fclose($input);
        }

        fclose($output);

        $extension      = pathinfo($upload->filename, PATHINFO_EXTENSION);
        $uuid           = Str::uuid()->toString();
        $storedFilename = $band->site_name . '/media/' . $uuid . '.' . $extension;
        $storageDisk    = config('filesystems.default');

        $stream = fopen($tempFullPath, 'rb');
        Storage::disk($storageDisk)->put($storedFilename, $stream, 'private');
        if (is_resource($stream)) {
            fclose($stream);
        }

        $mediaType = $this->determineMediaType($upload->mime_type);

        $mediaFile = MediaFile::create([
            'band_id'         => $band->id,
            'user_id'         => Auth::id(),
            'filename'        => $upload->filename,
            'stored_filename' => $storedFilename,
            'mime_type'       => $upload->mime_type,
            'file_size'       => $upload->filesize,
            'disk'            => $storageDisk,
            'media_type'      => $mediaType,
            'title'           => $upload->filename,
            'folder_path'     => $upload->folder_path,
        ]);

        $quota = BandStorageQuota::firstOrCreate(
            ['band_id' => $band->id],
            ['quota_limit' => 5368709120, 'quota_used' => 0]
        );
        $quota->increment('quota_used', $upload->filesize);

        // Resolve (and lazily create) the event's client-shared folder, reusing the
        // same idempotent rule the web controllers use. The mobile client only ever
        // sends event_id — folder path logic stays server-side.
        //
        // Tenant guard: only honour event_id when the event actually belongs to the
        // uploading band. A client could otherwise pass an event_id from another
        // band and have the media associated with — and folder-pathed from — a
        // foreign event. When the event doesn't belong to $band we fall through to
        // a plain no-event upload (no folder, no association to the foreign event).
        $eventId = null;
        if ($upload->event_id) {
            $event = Events::find($upload->event_id);
            if ($event && $event->eventable?->band_id === $band->id) {
                $eventId = $event->id;
                try {
                    if ($event->enable_portal_media_access && !$event->media_folder_path) {
                        $folderPath = $this->mediaService->createEventFolder($event);
                        $event->update(['media_folder_path' => $folderPath]);
                    }
                    if ($event->media_folder_path && !$mediaFile->folder_path) {
                        $mediaFile->update(['folder_path' => $event->media_folder_path]);
                    }
                } catch (\Exception $e) {
                    // Non-fatal: the MediaFile is already valid and will still be
                    // associated below. Match the thumbnail-generation stance and
                    // leave folder_path null rather than aborting the upload.
                    \Log::warning('Mobile upload: event folder creation failed', [
                        'media_id' => $mediaFile->id,
                        'event_id' => $event->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->mediaService->createAssociations($mediaFile, null, $eventId);

        if (in_array($mediaType, ['image', 'video'])) {
            try {
                $this->mediaService->generateThumbnail($mediaFile);
            } catch (\Exception $e) {
                \Log::warning('Mobile upload: thumbnail generation failed', [
                    'media_id' => $mediaFile->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $disk->deleteDirectory("chunks/{$upload->upload_id}");
        $disk->delete($tempPath);

        return $mediaFile;
    }

    public function determineMediaType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default                               => 'document',
        };
    }
}
