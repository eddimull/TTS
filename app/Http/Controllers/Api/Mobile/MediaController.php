<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\UploadChunkRequest;
use App\Http\Requests\Mobile\UploadInitiateRequest;
use App\Http\Traits\ServesByteRanges;
use App\Models\Bands;
use App\Models\BandStorageQuota;
use App\Models\ChunkedUpload;
use App\Models\MediaFile;
use App\Services\MediaLibraryService;
use App\Services\Mobile\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    use ServesByteRanges;

    public function __construct(
        protected MediaLibraryService $mediaService,
        protected MediaUploadService $uploadService,
    ) {}

    // ── Browse ─────────────────────────────────────────────────────────────────

    public function index(Request $request, Bands $band): JsonResponse
    {
        $filters = $request->only(['search', 'media_type', 'folder_path']);
        $perPage = min((int) $request->get('per_page', 24), 100);

        $query = $this->mediaService->search($band->id, $filters);
        $query->with(['tags:id,name,color', 'uploader:id,name']);

        $paginated = $query->paginate($perPage)->withQueryString();

        $subfolders = $this->mediaService->getSubfoldersOf($band->id, $filters['folder_path'] ?? null);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn ($m) => $this->formatFile($m)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'folders' => array_column($subfolders, 'path'),
        ]);
    }

    public function show(Bands $band, MediaFile $media): JsonResponse
    {
        if ($media->band_id !== $band->id) {
            abort(404);
        }

        $media->load(['tags:id,name,color', 'uploader:id,name']);

        return response()->json($this->formatFile($media, detailed: true));
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    public function destroy(Bands $band, MediaFile $media): JsonResponse
    {
        if ($media->band_id !== $band->id) {
            abort(404);
        }

        $media->delete();

        return response()->json(['ok' => true]);
    }

    // ── Create folder ──────────────────────────────────────────────────────

    public function createFolder(Request $request, Bands $band): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[^\/\\\\]+$/'],
        ]);

        // Folders are virtual — no DB record. We validate the name and return
        // the canonical folder_path string the client should use when uploading.
        $folderPath = trim($validated['name']);

        if ($folderPath === '') {
            return response()->json(['error' => 'Folder name cannot be empty.'], 422);
        }

        return response()->json(['folder_path' => $folderPath]);
    }

    // ── Serve / thumbnail ──────────────────────────────────────────────────────

    public function serve(Request $request, Bands $band, MediaFile $media)
    {
        if ($media->band_id !== $band->id) {
            abort(404);
        }

        try {
            return $this->streamWithByteRanges(
                $request,
                Storage::disk($media->disk),
                $media->stored_filename,
                [
                    'Content-Type'        => $media->mime_type,
                    'Content-Disposition' => 'inline; filename="' . $media->filename . '"',
                    'Cache-Control'       => 'private, max-age=86400',
                ],
            );
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }

    // ── Chunked upload (initiate) ──────────────────────────────────────────────

    public function uploadInitiate(UploadInitiateRequest $request, Bands $band): JsonResponse
    {
        $validated = $request->validated();

        $quota = BandStorageQuota::firstOrCreate(
            ['band_id' => $band->id],
            ['quota_limit' => 5368709120, 'quota_used' => 0]
        );

        if (($quota->quota_used + $validated['filesize']) > $quota->quota_limit) {
            return response()->json(['error' => 'Storage quota exceeded.'], 422);
        }

        $uploadId = Str::uuid()->toString();

        ChunkedUpload::create([
            'upload_id'    => $uploadId,
            'filename'     => $validated['filename'],
            'filesize'     => $validated['filesize'],
            'mime_type'    => $validated['mime_type'],
            'folder_path'  => $validated['folder_path'] ?? null,
            'event_id'     => $validated['event_id'] ?? null,
            'total_chunks' => $validated['total_chunks'],
            'user_id'      => Auth::id(),
            'band_id'      => $band->id,
            'status'       => 'initiated',
        ]);

        return response()->json(['upload_id' => $uploadId]);
    }

    // ── Chunked upload (chunk) ─────────────────────────────────────────────────

    public function uploadChunk(UploadChunkRequest $request, Bands $band, string $uploadId): JsonResponse
    {
        $validated = $request->validated();

        $upload = $this->findUploadForBand($uploadId, $band);

        if ($validated['chunk_index'] >= $upload->total_chunks) {
            return response()->json(['error' => 'Invalid chunk index.'], 400);
        }

        $chunkPath = "chunks/{$uploadId}/{$validated['chunk_index']}";
        Storage::disk('local')->put(
            $chunkPath,
            file_get_contents($request->file('chunk')->getRealPath())
        );

        $upload->increment('chunks_uploaded');
        $upload->update(['status' => 'uploading', 'last_chunk_at' => now()]);

        $fresh    = $upload->fresh();
        $progress = ($fresh->chunks_uploaded / $upload->total_chunks) * 100;

        return response()->json([
            'chunks_uploaded' => $fresh->chunks_uploaded,
            'progress'        => round($progress, 1),
        ]);
    }

    // ── Chunked upload (complete) ──────────────────────────────────────────────

    public function uploadComplete(Request $request, Bands $band, string $uploadId): JsonResponse
    {
        $upload = $this->findUploadForBand($uploadId, $band);

        if ($upload->chunks_uploaded !== $upload->total_chunks) {
            return response()->json([
                'error'    => 'Missing chunks.',
                'expected' => $upload->total_chunks,
                'received' => $upload->chunks_uploaded,
            ], 400);
        }

        try {
            $mediaFile = $this->uploadService->complete($upload, $band);
            $upload->update(['status' => 'completed', 'media_id' => $mediaFile->id]);

            return response()->json([
                'ok'    => true,
                'media' => $this->formatFile($mediaFile),
            ]);
        } catch (\Exception $e) {
            $upload->update(['status' => 'failed']);

            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    // ── Chunked upload (status) ────────────────────────────────────────────────

    public function uploadStatus(Bands $band, string $uploadId): JsonResponse
    {
        $upload = $this->findUploadForBand($uploadId, $band);

        return response()->json([
            'upload_id'       => $upload->upload_id,
            'filename'        => $upload->filename,
            'filesize'        => $upload->filesize,
            'mime_type'       => $upload->mime_type,
            'total_chunks'    => $upload->total_chunks,
            'chunks_uploaded' => $upload->chunks_uploaded,
            'status'          => $upload->status,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Resolve a chunked upload for the current user, scoped to the band in the
     * route. Band-stamped uploads (new rows) must match the band exactly; legacy
     * rows with a null band_id remain reachable so in-flight uploads don't break.
     */
    private function findUploadForBand(string $uploadId, Bands $band): ChunkedUpload
    {
        return ChunkedUpload::where('upload_id', $uploadId)
            ->where('user_id', Auth::id())
            ->where(function ($q) use ($band) {
                $q->whereNull('band_id')->orWhere('band_id', $band->id);
            })
            ->firstOrFail();
    }

    private function formatFile(MediaFile $m, bool $detailed = false): array
    {
        $data = [
            'id'             => $m->id,
            'filename'       => $m->filename,
            'title'          => $m->title,
            'description'    => $m->description,
            'media_type'     => $m->media_type,
            'mime_type'      => $m->mime_type,
            'file_size'      => $m->file_size,
            'formatted_size' => $m->formatted_size,
            'folder_path'    => $m->folder_path,
            'thumbnail_url'  => $m->thumbnail_url,
            'created_at'     => $m->created_at?->toIso8601String(),
        ];

        if ($m->relationLoaded('tags')) {
            $data['tags'] = $m->tags->map(fn ($t) => [
                'id'    => $t->id,
                'name'  => $t->name,
                'color' => $t->color,
            ]);
        }

        if ($detailed && $m->relationLoaded('uploader')) {
            $data['uploader'] = [
                'id'   => $m->uploader?->id,
                'name' => $m->uploader?->name,
            ];
        }

        return $data;
    }
}
