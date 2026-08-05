<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\StoreLodgingRequest;
use App\Http\Requests\Mobile\UpdateLodgingRequest;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\Lodging;
use App\Services\Mobile\LodgingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile lodging CRUD: hotel stays for a band, optionally linked to a booking
 * or a single event, each with a nested set of rooms.
 *
 * Authorization is handled at the route layer via the `mobile.band` middleware
 * with the `read:lodging` / `write:lodging` abilities. Subs pass that gate via
 * the User::canRead() lodging carve-out but only for stays attached to gigs
 * they're actually assigned to, so read paths here scope down further — see
 * LodgingService::scopeForSubs() / subCanSee() (shared with the web controller).
 */
class LodgingsController extends Controller
{
    public function __construct(private readonly LodgingService $lodgingService)
    {
    }

    /**
     * GET /api/mobile/bands/{band}/lodgings
     */
    public function index(Request $request, Bands $band): JsonResponse
    {
        $user = $request->user();

        $query = Lodging::where('band_id', $band->id)
            ->withCount(['rooms', 'attachments'])
            ->orderBy('check_in_at');

        $this->lodgingService->scopeForSubs($query, $user, $band);

        return response()->json([
            'lodgings'  => $query->get()->map(fn ($l) => $this->lodgingService->formatSummary($l))->values(),
            'can_write' => $user->canWrite('lodging', $band->id),
        ]);
    }

    /**
     * GET /api/mobile/bands/{band}/lodgings/{lodging}
     */
    public function show(Request $request, Bands $band, Lodging $lodging): JsonResponse
    {
        abort_if($lodging->band_id !== $band->id, 404);
        $user = $request->user();

        // Full members/owners see every stay; a sub only sees stays tied to a
        // gig they're on. 404 (not 403) so we don't leak the row's existence.
        if (!$user->bands()->contains('id', $band->id) && !$this->lodgingService->subCanSee($lodging)) {
            abort(404);
        }

        return response()->json([
            'lodging'   => $this->lodgingService->formatDetail($lodging),
            'can_write' => $user->canWrite('lodging', $band->id),
        ]);
    }

    /**
     * POST /api/mobile/bands/{band}/lodgings
     */
    public function store(StoreLodgingRequest $request, Bands $band): JsonResponse
    {
        $data = $request->validated();
        $rooms = $data['rooms'] ?? [];
        unset($data['rooms']);

        $this->assertLinksBelongToBand($data, $band->id);

        $lodging = DB::transaction(function () use ($data, $band, $rooms) {
            $lodging = Lodging::create($data + ['band_id' => $band->id]);
            $this->lodgingService->syncRooms($lodging, $rooms);
            return $lodging;
        });

        return response()->json(['lodging' => $this->lodgingService->formatDetail($lodging->fresh())], 201);
    }

    /**
     * PATCH /api/mobile/bands/{band}/lodgings/{lodging}
     */
    public function update(UpdateLodgingRequest $request, Bands $band, Lodging $lodging): JsonResponse
    {
        abort_if($lodging->band_id !== $band->id, 404);

        $data = $request->validated();
        $rooms = $data['rooms'] ?? null;
        unset($data['rooms']);

        $this->assertLinksBelongToBand($data, $band->id);

        if ($data !== []) {
            $lodging->update($data);
        }
        if ($rooms !== null) {
            DB::transaction(function () use ($lodging, $rooms) {
                $this->lodgingService->syncRooms($lodging, $rooms);
            });
            // touch() alone doesn't broadcast (BroadcastsBandChanges ignores
            // updated_at-only changes); rooms are a child table so the parent
            // row's own tracked columns don't change either. Force the signal.
            $lodging->touch();
            $lodging->broadcastRefresh();
        }

        return response()->json(['lodging' => $this->lodgingService->formatDetail($lodging->fresh())]);
    }

    /**
     * DELETE /api/mobile/bands/{band}/lodgings/{lodging}
     */
    public function destroy(Request $request, Bands $band, Lodging $lodging): JsonResponse
    {
        abort_if($lodging->band_id !== $band->id, 404);

        $lodging->delete();

        return response()->json(['message' => 'Lodging deleted.']);
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
}
