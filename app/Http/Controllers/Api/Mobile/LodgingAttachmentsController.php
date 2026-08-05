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
use Symfony\Component\HttpFoundation\HeaderUtils;

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
        // storeAs() returns false (not an exception) on a storage-driver
        // failure. Persisting that as stored_filename would create a
        // phantom attachment row that serves Content-Length: 0 forever
        // (Storage::get('0') returns null rather than throwing, so show()'s
        // catch never fires). Abort with a clear JSON error before the row
        // exists.
        abort_if($path === false, 500, 'Failed to store attachment file.');

        $attachment = LodgingAttachment::create([
            'lodging_id'      => $lodging->id,
            'filename'        => $file->getClientOriginalName(),
            'stored_filename' => $path,
            'mime_type'       => $file->getMimeType(),
            'file_size'       => $file->getSize(),
            'disk'            => $disk,
        ]);
        // touch() alone doesn't broadcast (BroadcastsBandChanges ignores
        // updated_at-only changes); the attachment lives on a child table so
        // the parent row's own tracked columns don't change. Force the signal.
        $lodging->touch();
        $lodging->broadcastRefresh();

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
        $lodging->broadcastRefresh();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    /**
     * GET /api/mobile/lodging-attachments/{attachment}
     *
     * Serve bytes with auth. Deliberately NOT the public /images/ proxy —
     * lodging attachments can contain confirmation numbers.
     *
     * `lodging()` (not the eager `->lodging` accessor) is resolved explicitly
     * so a soft-deleted parent stay (SoftDeletingScope excludes it by
     * default) 404s cleanly instead of dereferencing a null relation. A
     * deleted stay's attachments are gone as far as this endpoint is
     * concerned — the stay itself is gone.
     */
    public function show(Request $request, LodgingAttachment $attachment)
    {
        $lodging = $attachment->lodging()->first();
        abort_if(!$lodging, 404, 'File not found');

        $user = $request->user();
        $bandId = $lodging->band_id;
        if (!$user || !$user->canRead('lodging', $bandId)) {
            abort(403, 'You do not have permission to view this file');
        }

        // Full members/owners pass canRead() band-wide; a sub only passes the
        // canRead() carve-out but must additionally be tied to this specific
        // stay's gig — otherwise a sub assigned to one gig could fetch
        // another gig's attachments by enumerating attachment ids. Mirrors
        // LodgingsController::show()'s per-stay gate.
        if (!$user->bands()->contains('id', $bandId) && !$this->lodgingService->subCanSee($lodging)) {
            abort(404, 'File not found');
        }

        try {
            $file = Storage::disk($attachment->disk)->get($attachment->stored_filename);
            $disposition = $this->dispositionFor($attachment->mime_type);
            return response($file)
                ->header('Content-Type', $attachment->mime_type)
                ->header('Content-Disposition', $this->contentDisposition($disposition, $attachment->filename))
                ->header('Cache-Control', 'private, max-age=3600')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }

    /**
     * Inline rendering is safe (and desirable) for images/PDF; every other
     * mime forces a download so the browser never tries to execute/render
     * an arbitrary uploaded file type inline.
     */
    private function dispositionFor(?string $mimeType): string
    {
        if ($mimeType && (str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf')) {
            return 'inline';
        }
        return 'attachment';
    }

    /**
     * Build a safe Content-Disposition header. The stored filename is
     * user-controlled (uploader-supplied original filename), so it cannot be
     * concatenated directly into the header — a quote or CRLF in the
     * filename could otherwise inject headers or break out of the
     * filename="" token. HeaderUtils::makeDisposition() escapes the
     * quoted-string filename and additionally emits an RFC 5987
     * filename*=UTF-8'' fallback built from the sanitized ASCII name.
     */
    private function contentDisposition(string $disposition, string $filename): string
    {
        // Whitelist a conservative safe set (alnum, space, dot, dash,
        // underscore, parens) rather than blacklisting — anything outside
        // it (quotes, control chars, colons, etc.) becomes '_'. The real
        // filename is still sent verbatim (percent-encoded) via the RFC 5987
        // filename*= parameter that HeaderUtils::makeDisposition() adds.
        $fallback = preg_replace('/[^A-Za-z0-9 ._()-]/', '_', $filename);
        if ($fallback === '' || $fallback === null) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $fallback = $extension ? 'file.' . preg_replace('/[^A-Za-z0-9]/', '', $extension) : 'file';
        }

        return HeaderUtils::makeDisposition($disposition, $filename, $fallback);
    }
}
