<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\StoreChartRequest;
use App\Http\Requests\Mobile\StoreChartUploadRequest;
use App\Models\Bands;
use App\Models\Song;
use App\Models\Charts;
use App\Models\ChartUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class MusicController extends Controller
{
    /**
     * List all active songs for a band.
     */
    public function songs(Request $_request, Bands $band): JsonResponse
    {
        $songs = Song::where('band_id', $band->id)
            ->where('active', true)
            ->orderBy('title')
            ->get(['id', 'band_id', 'title', 'artist', 'song_key', 'genre', 'bpm']);

        return response()->json([
            'songs' => $songs->map(fn ($s) => [
                'id'       => $s->id,
                'band_id'  => $s->band_id,
                'title'    => $s->title ?? '',
                'artist'   => $s->artist ?? '',
                'song_key' => $s->song_key ?? '',
                'genre'    => $s->genre ?? '',
                'bpm'      => $s->bpm ?? 0,
            ])->values(),
        ]);
    }

    /**
     * List all charts for a band.
     */
    public function charts(Request $_request, Bands $band): JsonResponse
    {
        $charts = Charts::where('band_id', $band->id)
            ->withCount('uploads')
            ->orderBy('title')
            ->get();

        return response()->json([
            'charts' => $charts->map(fn ($ch) => [
                'id'            => $ch->id,
                'band_id'       => $ch->band_id,
                'title'         => $ch->title ?? '',
                'composer'      => $ch->composer ?? '',
                'description'   => $ch->description ?? '',
                'price'         => $ch->price ?? 0,
                'public'        => (bool) $ch->public,
                'uploads_count' => $ch->uploads_count ?? 0,
            ])->values(),
        ]);
    }

    /**
     * Show a single chart with its uploads.
     */
    public function chartDetail(Request $_request, Bands $band, Charts $chart): JsonResponse
    {
        // Ensure the chart belongs to this band
        if ((int) $chart->band_id !== (int) $band->id) {
            return response()->json(['message' => 'Chart not found.'], 404);
        }

        // Eager-load uploads with their type (already auto-loaded via $with, but explicit for clarity)
        $chart->loadMissing('uploads.type');

        return response()->json([
            'chart' => [
                'id'            => $chart->id,
                'band_id'       => $chart->band_id,
                'title'         => $chart->title ?? '',
                'composer'      => $chart->composer ?? '',
                'description'   => $chart->description ?? '',
                'price'         => $chart->price ?? 0,
                'public'        => (bool) $chart->public,
                'uploads_count' => $chart->uploads->count(),
                'uploads'       => $chart->uploads->map(fn ($u) => [
                    'id'           => $u->id,
                    'chart_id'     => $u->chart_id,
                    'display_name' => $u->displayName ?? '',
                    'notes'        => $u->notes ?? '',
                    'url'          => $u->url ?? '',
                    'file_type'    => $u->fileType ?? '',
                    'type_name'    => $u->type->name ?? '',
                ])->values(),
            ],
        ]);
    }

    /**
     * Create a new chart for a band.
     */
    public function storeChart(StoreChartRequest $request, Bands $band): JsonResponse
    {
        $chart = Charts::create([
            'band_id'     => $band->id,
            'title'       => $request->validated('title'),
            'composer'    => $request->validated('composer', ''),
            'description' => $request->validated('description', ''),
            'price'       => $request->validated('price', 0),
            'public'      => $request->boolean('is_public'),
        ]);

        $chart->loadMissing('uploads.type');

        return response()->json([
            'chart' => [
                'id'            => $chart->id,
                'band_id'       => $chart->band_id,
                'title'         => $chart->title ?? '',
                'composer'      => $chart->composer ?? '',
                'description'   => $chart->description ?? '',
                'price'         => $chart->price ?? 0,
                'public'        => (bool) $chart->public,
                'uploads_count' => $chart->uploads->count(),
                'uploads'       => $chart->uploads->map(fn ($u) => [
                    'id'           => $u->id,
                    'chart_id'     => $u->chart_id,
                    'display_name' => $u->displayName ?? '',
                    'notes'        => $u->notes ?? '',
                    'url'          => $u->url ?? '',
                    'file_type'    => $u->fileType ?? '',
                    'type_name'    => $u->type->name ?? '',
                ])->values(),
            ],
        ], 201);
    }

    /**
     * Upload a file to a chart.
     */
    public function storeChartUpload(StoreChartUploadRequest $request, Bands $band, Charts $chart): JsonResponse
    {
        if ((int) $chart->band_id !== (int) $band->id) {
            return response()->json(['message' => 'Chart not found.'], 404);
        }

        $file = $request->file('file');

        // Build the S3 path using the same pattern as ChartsServices::uploadData()
        $dataPath       = $band->site_name . '/charts/';
        $originalName   = $file->getClientOriginalName();
        $sanitizedName  = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $originalName);
        $timestamp      = Carbon::now()->timestamp;
        $randomString   = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
        $uploadPath     = $dataPath . $timestamp . '_' . $randomString . '_' . $sanitizedName;

        // Upload to S3
        $fileContents = file_get_contents($file->getRealPath());

        try {
            if (!Storage::disk('s3')->put($uploadPath, $fileContents)) {
                return response()->json(['message' => 'Failed to upload file to storage.'], 500);
            }
        } catch (\Exception $e) {
            \Log::error('S3 chart upload failed (mobile)', [
                'error' => $e->getMessage(),
                'file'  => $originalName,
            ]);
            return response()->json(['message' => 'Failed to upload file to storage.'], 500);
        }

        // Create database record
        try {
            $upload = ChartUploads::create([
                'chart_id'       => $chart->id,
                'upload_type_id' => $request->validated('upload_type_id'),
                'name'           => $sanitizedName,
                'displayName'    => $request->validated('display_name'),
                'fileType'       => $file->getMimeType(),
                'url'            => $uploadPath,
                'notes'          => $request->validated('notes', ''),
            ]);
        } catch (\Exception $e) {
            // Clean up the S3 file if DB insert fails
            Storage::disk('s3')->delete($uploadPath);
            \Log::error('Chart upload DB insert failed (mobile)', [
                'error'    => $e->getMessage(),
                'chart_id' => $chart->id,
            ]);
            return response()->json(['message' => 'Failed to save upload record.'], 500);
        }

        $upload->loadMissing('type');

        return response()->json([
            'upload' => [
                'id'           => $upload->id,
                'chart_id'     => $upload->chart_id,
                'display_name' => $upload->displayName ?? '',
                'notes'        => $upload->notes ?? '',
                'url'          => $upload->url ?? '',
                'file_type'    => $upload->fileType ?? '',
                'type_name'    => $upload->type->name ?? '',
            ],
        ], 201);
    }

    /**
     * Delete a chart and cascade-delete its uploads (including S3 files).
     */
    public function destroyChart(Request $_request, Bands $band, Charts $chart): JsonResponse
    {
        if ((int) $chart->band_id !== (int) $band->id) {
            return response()->json(['message' => 'Chart not found.'], 404);
        }

        // Delete S3 files for each upload
        foreach ($chart->uploads as $upload) {
            try {
                if ($upload->url && Storage::disk('s3')->exists($upload->url)) {
                    Storage::disk('s3')->delete($upload->url);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to delete S3 file during chart deletion', [
                    'upload_id' => $upload->id,
                    'url'       => $upload->url,
                    'error'     => $e->getMessage(),
                ]);
                // Continue deleting remaining uploads even if one S3 delete fails
            }
        }

        // Delete all upload DB records, then the chart itself
        $chart->uploads()->delete();
        $chart->delete();

        return response()->json(['message' => 'Chart deleted.']);
    }

    /**
     * Stream a chart upload file through the API (no direct S3 access).
     */
    public function downloadChartUpload(Request $_request, Bands $band, Charts $chart, ChartUploads $upload)
    {
        if ((int) $chart->band_id !== (int) $band->id) {
            return response()->json(['message' => 'Chart not found.'], 404);
        }

        if ((int) $upload->chart_id !== (int) $chart->id) {
            return response()->json(['message' => 'Upload not found.'], 404);
        }

        if (!$upload->url || !Storage::disk('s3')->exists($upload->url)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        try {
            $contents = Storage::disk('s3')->get($upload->url);
            $mimeType = $upload->fileType ?: 'application/octet-stream';
            $filename = $upload->displayName ?: basename($upload->url);

            return response($contents, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
                ->header('Cache-Control', 'no-cache, must-revalidate');
        } catch (\Exception $e) {
            \Log::error('Mobile chart file download failed', [
                'upload_id' => $upload->id,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Unable to download file.'], 500);
        }
    }

    /**
     * Delete a single upload from a chart (including the S3 file).
     */
    public function destroyChartUpload(Request $_request, Bands $band, Charts $chart, ChartUploads $upload): JsonResponse
    {
        if ((int) $chart->band_id !== (int) $band->id) {
            return response()->json(['message' => 'Chart not found.'], 404);
        }

        if ((int) $upload->chart_id !== (int) $chart->id) {
            return response()->json(['message' => 'Upload not found.'], 404);
        }

        // Delete S3 file
        try {
            if ($upload->url && Storage::disk('s3')->exists($upload->url)) {
                Storage::disk('s3')->delete($upload->url);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to delete S3 file during upload deletion', [
                'upload_id' => $upload->id,
                'url'       => $upload->url,
                'error'     => $e->getMessage(),
            ]);
            // Proceed with DB deletion even if S3 fails
        }

        $upload->delete();

        return response()->json(['message' => 'Upload deleted.']);
    }
}
