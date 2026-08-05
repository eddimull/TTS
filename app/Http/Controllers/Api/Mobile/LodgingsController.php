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
use App\Services\UserEventsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile lodging CRUD: hotel stays for a band, optionally linked to a booking
 * or a single event, each with a nested set of rooms.
 *
 * Authorization is handled at the route layer via the `mobile.band` middleware
 * with the `read:lodging` / `write:lodging` abilities. Subs pass that gate via
 * the User::canRead() lodging carve-out but only for stays attached to gigs
 * they're actually assigned to, so read paths here scope down further — see
 * scopeForSubs() / subCanSee().
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

        $this->scopeForSubs($query, $user, $band);

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
        if (!$user->bands()->contains('id', $band->id) && !$this->subCanSee($lodging)) {
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

        $lodging = Lodging::create($data + ['band_id' => $band->id]);
        $this->lodgingService->syncRooms($lodging, $rooms);

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
            $this->lodgingService->syncRooms($lodging, $rooms);
            $lodging->touch(); // broadcast one parent update for room changes
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

    /**
     * Sub-only users see only stays linked to their assigned gigs.
     *
     * UserEventsService::getEventIds() returns a plain array (it ends in
     * ->all()), so array semantics are used throughout here.
     */
    private function scopeForSubs($query, $user, $band): void
    {
        if ($user->bands()->contains('id', $band->id)) {
            return; // full member/owner — no scoping
        }
        $eventIds = app(UserEventsService::class)->getEventIds(Carbon::now()->subYear());
        $bookingIds = Events::whereIn('id', $eventIds)
            ->where('eventable_type', Bookings::class)
            ->pluck('eventable_id');

        $query->where(function ($q) use ($eventIds, $bookingIds) {
            $q->whereIn('event_id', $eventIds)
              ->orWhereIn('booking_id', $bookingIds);
        });
    }

    /**
     * Can the *authenticated* user (UserEventsService resolves them via
     * Auth::user() internally) see this single stay as a sub?
     */
    private function subCanSee(Lodging $lodging): bool
    {
        if (!$lodging->event_id && !$lodging->booking_id) {
            return false;
        }
        $eventIds = app(UserEventsService::class)->getEventIds(Carbon::now()->subYear());
        if ($lodging->event_id && in_array((int) $lodging->event_id, $eventIds, true)) {
            return true;
        }
        if ($lodging->booking_id) {
            return Events::whereIn('id', $eventIds)
                ->where('eventable_type', Bookings::class)
                ->where('eventable_id', $lodging->booking_id)
                ->exists();
        }

        return false;
    }
}
