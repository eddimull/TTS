<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\MediaFile;
use App\Models\MediaTag;
use App\Models\BandStorageQuota;
use App\Models\GoogleDriveConnection;
use App\Services\MediaLibraryService;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Http\Requests\Media\BulkMoveRequest;
use App\Http\Requests\Media\CreateFolderRequest;
use App\Http\Requests\Media\RenameFolderRequest;
use App\Http\Requests\Media\DeleteFolderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MediaLibraryController extends Controller
{
    public function __construct(
        protected MediaLibraryService $mediaService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $bands = $user->bands();
        $currentBandId = $request->get('band_id', $bands->first()->id ?? null);

        if (!$currentBandId) {
            return redirect()->route('dashboard')
                ->with('errorMessage', 'No band available. Please create or join a band first.');
        }

        if (!$user->canRead('media', $currentBandId)) {
            abort(403, 'You do not have permission to view media');
        }

        $filters = $request->only(['search', 'media_type', 'tags', 'sort_by', 'sort_order', 'folder_path']);

        // Check if viewing a system folder
        $folderPath = $filters['folder_path'] ?? null;
        if ($folderPath && in_array($folderPath, ['charts', 'contracts', 'event_uploads'])) {
            // Get system folder files
            $systemFiles = $this->mediaService->getSystemFolderFiles($currentBandId, $folderPath, $filters);

            // Manual pagination for system files
            $page = $request->get('page', 1);
            $perPage = 24;
            $total = $systemFiles->count();
            $items = $systemFiles->forPage($page, $perPage)->values();

            $media = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            // Regular media files
            \Log::info('Before search', [
                'band_id' => $currentBandId,
                'filters' => $filters
            ]);

            $mediaQuery = $this->mediaService->search($currentBandId, $filters);

            \Log::info('After search - before paginate', [
                'query_count' => $mediaQuery->count(),
                'sql' => $mediaQuery->toSql(),
                'bindings' => $mediaQuery->getBindings()
            ]);

            $media = $mediaQuery->paginate(24)->withQueryString();

            // DEBUG: Log what we're returning
            \Log::info('Media Index Debug - After paginate', [
                'folder_path' => $filters['folder_path'] ?? 'NULL',
                'media_count' => $media->count(),
                'total' => $media->total(),
                'items' => $media->items(),
                'files' => $media->pluck('id', 'filename')->toArray()
            ]);
        }

        $tags = MediaTag::where('band_id', $currentBandId)
            ->withCount('mediaFiles')
            ->orderBy('name')
            ->get();

        $folders = $this->mediaService->getFolders($currentBandId);

        // Get subfolders for current path (for inline display)
        $subfolders = $this->mediaService->getSubfoldersOf($currentBandId, $folderPath);

        $quota = BandStorageQuota::firstOrCreate(
            ['band_id' => $currentBandId],
            ['quota_limit' => 5368709120, 'quota_used' => 0]
        );

        $currentBand = Bands::find($currentBandId);
        $bookings = $currentBand->bookings()
            ->select('id', 'name', 'date')
            ->orderBy('date', 'desc')
            ->limit(100)
            ->get()
            ->map(fn($booking) => [
                'id' => $booking->id,
                'name' => $booking->name,
                'date' => $booking->date,
            ]);

        $events = \App\Models\Events::whereHas('eventable', function ($query) use ($currentBandId) {
            $query->where('band_id', $currentBandId);
        })
            ->select('id', 'title', 'date')
            ->orderBy('date', 'desc')
            ->limit(100)
            ->get()
            ->map(fn($event) => [
                'id' => $event->id,
                'name' => $event->title,
                'date' => $event->date,
            ]);

        // Get Google Drive connections for this band
        $driveConnections = GoogleDriveConnection::where('band_id', $currentBandId)
            ->with(['user:id,name', 'folders'])
            ->get();

        return Inertia::render('Media/Index', [
            'media' => $media,
            'tags' => $tags,
            'folders' => $folders,
            'subfolders' => $subfolders,
            'quota' => [
                'used' => $quota->quota_used,
                'limit' => $quota->quota_limit,
                'formatted_used' => $quota->getFormattedUsed(),
                'formatted_limit' => $quota->getFormattedLimit(),
                'percentage' => $quota->getUsagePercentage()
            ],
            'filters' => $filters,
            'availableBands' => $bands,
            'currentBandId' => $currentBandId,
            'bookings' => $bookings,
            'events' => $events,
            'driveConnections' => $driveConnections,
        ]);
    }

    public function upload(UploadMediaRequest $request)
    {
        $band = Bands::findOrFail($request->band_id);
        $uploadedCount = 0;

        foreach ($request->file('files') as $file) {
            try {
                $mediaFile = $this->mediaService->uploadFile(
                    $band,
                    $file,
                    Auth::id(),
                    $request->only(['title', 'description', 'folder_path'])
                );

                if ($request->tags) {
                    $mediaFile->tags()->sync($request->tags);
                }

                $this->mediaService->createAssociations(
                    $mediaFile,
                    $request->booking_id,
                    $request->event_id
                );

                $uploadedCount++;
            } catch (\Exception $e) {
                \Log::error('Media upload failed', [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ]);

                return redirect()->back()->with('errorMessage', 'Upload failed: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('successMessage', "Successfully uploaded {$uploadedCount} file(s)");
    }

    public function show(MediaFile $media)
    {
        $user = Auth::user();

        if (!$user->canRead('media', $media->band_id)) {
            abort(403, 'You do not have permission to view this media');
        }

        $media->load(['tags', 'uploader', 'associations.associable', 'shares']);

        return Inertia::render('Media/Show', [
            'media' => $media,
            'canEdit' => $user->canWrite('media', $media->band_id)
        ]);
    }

    public function update(UpdateMediaRequest $request, MediaFile $media)
    {
        $this->mediaService->updateMedia(
            $media,
            $request->only(['title', 'description', 'folder_path', 'tags'])
        );

        return redirect()->back()->with('successMessage', 'Media updated successfully');
    }

    public function destroy(MediaFile $media)
    {
        $user = Auth::user();

        if (!$user->canWrite('media', $media->band_id)) {
            abort(403, 'You do not have permission to delete this media');
        }

        $media->delete();

        return redirect()->route('media.index', ['band_id' => $media->band_id])
            ->with('successMessage', 'Media deleted successfully');
    }

    public function serve(MediaFile $media)
    {
        $user = Auth::user();

        if (!$user || !$user->canRead('media', $media->band_id)) {
            abort(403, 'You do not have permission to view this file');
        }

        try {
            $file = Storage::disk($media->disk)->get($media->stored_filename);

            return response($file)
                ->header('Content-Type', $media->mime_type)
                ->header('Content-Disposition', 'inline; filename="' . $media->filename . '"')
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Exception $e) {
            \Log::error('Failed to serve media file', [
                'media_id' => $media->id,
                'error' => $e->getMessage()
            ]);
            abort(404, 'File not found');
        }
    }

    public function download($id)
    {
        $user = Auth::user();

        // Check if it's a system file (chart, contract, event_upload)
        if (str_starts_with($id, 'chart_')) {
            $chartUploadId = (int) str_replace('chart_', '', $id);
            $chartUpload = DB::table('chart_uploads')
                ->join('charts', 'chart_uploads.chart_id', '=', 'charts.id')
                ->where('chart_uploads.id', $chartUploadId)
                ->select('chart_uploads.*', 'charts.band_id', 'charts.title')
                ->first();

            if (!$chartUpload || !$user->canRead('media', $chartUpload->band_id)) {
                abort(403, 'You do not have permission to download this file');
            }

            try {
                $path = ltrim($chartUpload->url, '/');
                $file = Storage::disk('s3')->get($path);
                $filename = $chartUpload->displayName ?? basename($path);

                return response($file)
                    ->header('Content-Type', $chartUpload->fileType)
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                    ->header('Cache-Control', 'no-cache');
            } catch (\Exception $e) {
                abort(404, 'File not found');
            }
        } elseif (str_starts_with($id, 'contract_')) {
            $contractId = (int) str_replace('contract_', '', $id);
            $contract = DB::table('contracts')
                ->join('bookings', function ($join) {
                    $join->on('contracts.contractable_id', '=', 'bookings.id')
                         ->where('contracts.contractable_type', '=', 'App\Models\Bookings');
                })
                ->where('contracts.id', $contractId)
                ->select('contracts.*', 'bookings.band_id', 'bookings.name')
                ->first();

            if (!$contract || !$user->canRead('media', $contract->band_id)) {
                abort(403, 'You do not have permission to download this file');
            }

            try {
                $path = ltrim($contract->asset_url, '/');
                $file = Storage::disk('s3')->get($path);
                $filename = $contract->name . '_contract.pdf';

                return response($file)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                    ->header('Cache-Control', 'no-cache');
            } catch (\Exception $e) {
                abort(404, 'File not found');
            }
        } elseif (str_starts_with($id, 'event_upload_')) {
            $attachmentId = (int) str_replace('event_upload_', '', $id);
            $attachment = \App\Models\EventAttachment::with('event.eventable')->find($attachmentId);

            if (!$attachment || !$user->canRead('media', $attachment->event->eventable->band_id)) {
                abort(403, 'You do not have permission to download this file');
            }

            try {
                $file = Storage::disk($attachment->disk)->get($attachment->stored_filename);

                return response($file)
                    ->header('Content-Type', $attachment->mime_type)
                    ->header('Content-Disposition', 'attachment; filename="' . $attachment->filename . '"')
                    ->header('Cache-Control', 'no-cache');
            } catch (\Exception $e) {
                abort(404, 'File not found');
            }
        } else {
            // Regular media file
            $media = MediaFile::findOrFail($id);

            if (!$user->canRead('media', $media->band_id)) {
                abort(403, 'You do not have permission to download this file');
            }

            try {
                $file = Storage::disk($media->disk)->get($media->stored_filename);

                return response($file)
                    ->header('Content-Type', $media->mime_type)
                    ->header('Content-Disposition', 'attachment; filename="' . $media->filename . '"')
                    ->header('Cache-Control', 'no-cache');
            } catch (\Exception $e) {
                \Log::error('Failed to download media file', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage()
                ]);
                abort(404, 'File not found');
            }
        }
    }

    public function thumbnail(MediaFile $media)
    {
        $user = Auth::user();

        if (!$user || !$user->canRead('media', $media->band_id)) {
            abort(403, 'You do not have permission to view this file');
        }

        if ($media->media_type !== 'image' && $media->media_type !== 'video') {
            abort(404, 'Thumbnail not available for this file type');
        }

        $thumbnailPath = str_replace(
            '.' . pathinfo($media->stored_filename, PATHINFO_EXTENSION),
            '_thumb.jpg',
            $media->stored_filename
        );

        try {
            if (!Storage::disk($media->disk)->exists($thumbnailPath)) {
                $file = Storage::disk($media->disk)->get($media->stored_filename);
                return response($file)
                    ->header('Content-Type', $media->mime_type)
                    ->header('Cache-Control', 'public, max-age=3600');
            }

            $file = Storage::disk($media->disk)->get($thumbnailPath);

            return response($file)
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Exception $e) {
            abort(404, 'Thumbnail not found');
        }
    }

    public function createFolder(CreateFolderRequest $request)
    {
        $this->mediaService->createFolder(
            $request->band_id,
            $request->folder_path,
            Auth::id()
        );

        return redirect()->back()->with('successMessage', 'Folder created successfully');
    }

    public function renameFolder(RenameFolderRequest $request)
    {
        $updated = $this->mediaService->renameFolder(
            $request->band_id,
            $request->old_path,
            $request->new_path
        );

        return redirect()->back()->with('successMessage', "Renamed folder and updated {$updated} file(s)");
    }

    public function deleteFolder(DeleteFolderRequest $request)
    {
        $updated = $this->mediaService->deleteFolder(
            $request->band_id,
            $request->folder_path
        );

        return redirect()->back()->with('successMessage', "Deleted folder and moved {$updated} file(s) to root");
    }

    public function bulkMove(BulkMoveRequest $request)
    {
        $updated = $this->mediaService->bulkMove(
            $request->band_id,
            $request->media_ids,
            $request->folder_path
        );

        $destination = $request->folder_path ? "to '{$request->folder_path}'" : "to root";
        return redirect()->back()->with('successMessage', "Moved {$updated} file(s) {$destination}");
    }
}
