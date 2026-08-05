<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLodgingWebRequest;
use App\Http\Requests\UpdateLodgingWebRequest;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\Lodging;
use App\Models\LodgingAttachment;
use App\Services\Mobile\LodgingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Web (Inertia) counterpart to Api\Mobile\LodgingsController /
 * LodgingAttachmentsController. Session-authenticated; attachments are
 * served through these web routes (not the sanctum mobile serve URL), so
 * formatForWeb()/formatWebAttachment() override LodgingService::formatDetail()'s
 * attachment URLs.
 */
class LodgingController extends Controller
{
    public function __construct(private readonly LodgingService $lodgingService)
    {
    }

    /** Cross-band index: every band the user can read lodging for. */
    public function index()
    {
        $user = auth()->user();
        $bands = $user->allBands()->map(function ($band) use ($user) {
            if (!$user->canRead('lodging', $band->id)) {
                return null;
            }
            $query = Lodging::where('band_id', $band->id)
                ->withCount(['rooms', 'attachments'])
                ->orderBy('check_in_at');
            $this->lodgingService->scopeForSubs($query, $user, $band);

            $lodgings = $query->get()->map(fn ($l) => $this->lodgingService->formatSummary($l))->values();
            return [
                'id'       => $band->id,
                'name'     => $band->name,
                'canWrite' => $user->canWrite('lodging', $band->id),
                'lodgings' => $lodgings,
            ];
        })->filter()->values();

        return inertia('Lodging/Index', ['bands' => $bands]);
    }

    public function create(Bands $band)
    {
        $this->authorizeWrite($band->id);

        return inertia('Lodging/Form', [
            'band'     => ['id' => $band->id, 'name' => $band->name],
            'lodging'  => null,
            'bookings' => $band->bookings()->orderByDesc('date')->get(['id', 'name', 'date']),
            'events'   => $this->bandEventOptions($band),
        ]);
    }

    public function store(StoreLodgingWebRequest $request, Bands $band)
    {
        $this->authorizeWrite($band->id);

        $data = $request->validated();
        $rooms = $data['rooms'] ?? [];
        unset($data['rooms']);

        $this->assertLinksBelongToBand($data, $band->id);

        $lodging = Lodging::create($data + ['band_id' => $band->id]);
        $this->lodgingService->syncRooms($lodging, $rooms);

        return redirect()->route('lodgings.show', $lodging)->with('success', 'Lodging created');
    }

    public function show(Lodging $lodging)
    {
        $user = auth()->user();
        $this->authorizeReadForLodging($lodging, $user);

        return inertia('Lodging/Show', [
            'band'     => ['id' => $lodging->band_id, 'name' => $lodging->band->name],
            'lodging'  => $this->formatForWeb($lodging),
            'canWrite' => $user->canWrite('lodging', $lodging->band_id),
        ]);
    }

    public function edit(Lodging $lodging)
    {
        $this->authorizeWrite($lodging->band_id);
        $band = $lodging->band;

        return inertia('Lodging/Form', [
            'band'     => ['id' => $band->id, 'name' => $band->name],
            'lodging'  => $this->formatForWeb($lodging),
            'bookings' => $band->bookings()->orderByDesc('date')->get(['id', 'name', 'date']),
            'events'   => $this->bandEventOptions($band),
        ]);
    }

    public function update(UpdateLodgingWebRequest $request, Lodging $lodging)
    {
        $this->authorizeWrite($lodging->band_id);

        $data = $request->validated();
        $rooms = $data['rooms'] ?? null;
        unset($data['rooms']);

        $this->assertLinksBelongToBand($data, $lodging->band_id);

        if ($data !== []) {
            $lodging->update($data);
        }
        if ($rooms !== null) {
            $this->lodgingService->syncRooms($lodging, $rooms);
            // touch() alone doesn't broadcast (BroadcastsBandChanges ignores
            // updated_at-only changes); rooms are a child table so the parent
            // row's own tracked columns don't change either. Force the signal.
            $lodging->touch();
            $lodging->broadcastRefresh();
        }

        return redirect()->route('lodgings.show', $lodging)->with('success', 'Lodging updated');
    }

    public function destroy(Lodging $lodging)
    {
        $this->authorizeWrite($lodging->band_id);
        $lodging->delete();

        return redirect()->route('lodgings.index')->with('success', 'Lodging deleted');
    }

    public function uploadAttachment(Lodging $lodging)
    {
        $this->authorizeWrite($lodging->band_id);
        request()->validate(['files.*' => 'required|file|max:10240']);

        $band = $lodging->band;
        $disk = config('filesystems.default');
        foreach (request()->file('files', []) as $file) {
            $extension = $file->getClientOriginalExtension();
            $filename  = Str::uuid() . ($extension ? '.' . $extension : '');
            $path      = $file->storeAs($band->site_name . '/lodging_uploads', $filename, $disk);
            LodgingAttachment::create([
                'lodging_id'      => $lodging->id,
                'filename'        => $file->getClientOriginalName(),
                'stored_filename' => $path,
                'mime_type'       => $file->getMimeType(),
                'file_size'       => $file->getSize(),
                'disk'            => $disk,
            ]);
        }
        // touch() alone doesn't broadcast (BroadcastsBandChanges ignores
        // updated_at-only changes); force the signal, mirroring the mobile
        // attachment controller.
        $lodging->touch();
        $lodging->broadcastRefresh();

        return response()->json(['attachments' => $lodging->attachments()->get()
            ->map(fn ($a) => $this->formatWebAttachment($a))->values()]);
    }

    public function showAttachment(LodgingAttachment $attachment)
    {
        $this->authorizeRead($attachment->lodging->band_id);
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

    public function downloadAttachment(LodgingAttachment $attachment)
    {
        $this->authorizeRead($attachment->lodging->band_id);
        try {
            return Storage::disk($attachment->disk)->download($attachment->stored_filename, $attachment->filename);
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }

    public function destroyAttachment(LodgingAttachment $attachment)
    {
        $this->authorizeWrite($attachment->lodging->band_id);
        $lodging = $attachment->lodging;
        $attachment->delete();
        $lodging->touch();
        $lodging->broadcastRefresh();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function authorizeRead(int $bandId): void
    {
        abort_unless(auth()->user()?->canRead('lodging', $bandId), 403, 'Unauthorized');
    }

    private function authorizeWrite(int $bandId): void
    {
        abort_unless(auth()->user()?->canWrite('lodging', $bandId), 403, 'Unauthorized');
    }

    /**
     * Single-record read gate for show(): a full member/owner passes via
     * canRead(); a sub passes canRead() too (the lodging carve-out) but must
     * additionally be tied to this specific stay's gig — mirrors the mobile
     * LodgingsController::show() 404-not-403 shape, except web returns 403
     * uniformly per the brief's test (no row-existence leak concern here
     * since band ownership already 403s strangers before reaching the sub
     * check).
     */
    private function authorizeReadForLodging(Lodging $lodging, $user): void
    {
        abort_unless($user, 403, 'Unauthorized');
        abort_unless($user->canRead('lodging', $lodging->band_id), 403, 'Unauthorized');

        if ($user->bands()->contains('id', $lodging->band_id)) {
            return; // full member/owner
        }

        abort_unless($this->lodgingService->subCanSee($lodging), 403, 'Unauthorized');
    }

    /** Reject booking_id/event_id pointing outside this band. */
    private function assertLinksBelongToBand(array $data, int $bandId): void
    {
        if (!empty($data['booking_id'])) {
            abort_unless(
                Bookings::where('id', $data['booking_id'])->where('band_id', $bandId)->exists(),
                422,
                'Booking does not belong to this band.'
            );
        }
        if (!empty($data['event_id'])) {
            $event = Events::with('eventable')->find($data['event_id']);
            abort_unless(
                $event && (int) ($event->eventable?->band_id) === $bandId,
                422,
                'Event does not belong to this band.'
            );
        }
    }

    private function formatForWeb(Lodging $lodging): array
    {
        $detail = $this->lodgingService->formatDetail($lodging);
        // Web serves attachments through session-auth web routes, not sanctum.
        $detail['attachments'] = $lodging->attachments->map(fn ($a) => $this->formatWebAttachment($a))->values()->toArray();
        return $detail;
    }

    private function formatWebAttachment(LodgingAttachment $a): array
    {
        return [
            'id'           => $a->id,
            'filename'     => $a->filename,
            'mime_type'    => $a->mime_type,
            'file_size'    => $a->file_size,
            'url'          => route('lodgings.attachments.show', $a),
            'download_url' => route('lodgings.attachments.download', $a),
        ];
    }

    /** Upcoming + recent events for the link picker. */
    private function bandEventOptions(Bands $band): array
    {
        return Events::query()
            ->whereHasMorph('eventable', [Bookings::class], fn ($q) => $q->where('band_id', $band->id))
            ->where('date', '>=', now()->subMonths(3)->toDateString())
            ->orderBy('date')
            ->get(['id', 'title', 'date'])
            ->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'date' => $e->date])
            ->toArray();
    }
}
