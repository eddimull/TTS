<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\UploadLodgingAttachmentRequest;
use App\Models\Bands;
use App\Models\Lodging;
use App\Models\LodgingAttachment;
use App\Services\Mobile\LodgingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LodgingAttachmentsController extends Controller
{
    public function __construct(private readonly LodgingService $lodgingService)
    {
    }

    /**
     * POST /api/mobile/bands/{band}/lodgings/{lodging}/attachments
     */
    public function store(UploadLodgingAttachmentRequest $request, Bands $band, Lodging $lodging): JsonResponse
    {
        abort_if($lodging->band_id !== $band->id, 404);

        $file      = $request->file('file');
        $disk      = config('filesystems.default');
        $extension = $file->getClientOriginalExtension();
        $filename  = Str::uuid() . ($extension ? '.' . $extension : '');
        $path      = $file->storeAs($band->site_name . '/lodging_uploads', $filename, $disk);

        $attachment = LodgingAttachment::create([
            'lodging_id'      => $lodging->id,
            'filename'        => $file->getClientOriginalName(),
            'stored_filename' => $path,
            'mime_type'       => $file->getMimeType(),
            'file_size'       => $file->getSize(),
            'disk'            => $disk,
        ]);
        $lodging->touch(); // broadcast parent update

        return response()->json(['attachment' => $this->lodgingService->formatAttachment($attachment)], 201);
    }

    /**
     * DELETE /api/mobile/bands/{band}/lodgings/{lodging}/attachments/{attachment}
     */
    public function destroy(Request $request, Bands $band, Lodging $lodging, LodgingAttachment $attachment): JsonResponse
    {
        abort_if($lodging->band_id !== $band->id, 404);
        abort_if($attachment->lodging_id !== $lodging->id, 404);

        $attachment->delete(); // model hook removes the stored file
        $lodging->touch();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    /**
     * GET /api/mobile/lodging-attachments/{attachment}
     *
     * Serve bytes with auth. Deliberately NOT the public /images/ proxy —
     * lodging attachments can contain confirmation numbers.
     */
    public function show(Request $request, LodgingAttachment $attachment)
    {
        $user = $request->user();
        $bandId = $attachment->lodging->band_id;
        if (!$user || !$user->canRead('lodging', $bandId)) {
            abort(403, 'You do not have permission to view this file');
        }

        try {
            $file = Storage::disk($attachment->disk)->get($attachment->stored_filename);
            return response($file)
                ->header('Content-Type', $attachment->mime_type)
                ->header('Content-Disposition', 'inline; filename="' . $attachment->filename . '"')
                ->header('Cache-Control', 'private, max-age=3600');
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }
}
