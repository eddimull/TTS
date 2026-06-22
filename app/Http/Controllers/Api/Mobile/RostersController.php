<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Roster;
use App\Services\RosterReconcileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile equivalent of {@see \App\Http\Controllers\RosterController} (JSON-only).
 *
 * Band ownership is enforced by the `owner` middleware on the route group.
 * Every {band}-scoped route also confirms the bound {roster} belongs to the
 * band (404 if not), since `owner` only guards the band itself.
 */
class RostersController extends Controller
{
    public function index(Bands $band): JsonResponse
    {
        $rosters = $band->rosters()
            ->withCount('members')
            ->with(['members' => fn ($query) => $query->where('is_active', true)->with(['user', 'bandRole'])])
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['rosters' => $rosters]);
    }

    public function show(Bands $band, Roster $roster): JsonResponse
    {
        $this->ensureRosterBelongsToBand($band, $roster);

        $roster->load([
            'slots.bandRole',
            'slots.activeMembers.user',
            'slots.activeMembers.bandRole',
            'members.user',
            'members.bandRole',
            'members.slot',
            'band',
        ]);

        return response()->json([
            'roster' => $roster,
            'slots' => $roster->slots->map(fn ($slot) => [
                'id'             => $slot->id,
                'name'           => $slot->name,
                'band_role_id'   => $slot->band_role_id,
                'band_role_name' => $slot->bandRole?->name,
                'is_required'    => $slot->is_required,
                'quantity'       => $slot->quantity,
                'notes'          => $slot->notes,
                'member_count'   => $slot->activeMembers->count(),
            ]),
            'members' => $roster->members->map(fn ($member) => [
                'id'           => $member->id,
                'user_id'      => $member->user_id,
                'slot_id'      => $member->slot_id,
                'name'         => $member->display_name,
                'email'        => $member->display_email,
                'phone'        => $member->phone,
                'role'         => $member->role_name,
                'band_role_id' => $member->band_role_id,
                'notes'        => $member->notes,
                'is_active'    => $member->is_active,
                'is_user'      => $member->isUser(),
            ]),
        ]);
    }

    public function store(Request $request, Bands $band): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $roster = Roster::create([
            'band_id' => $band->id,
            'name' => $request->name,
            'description' => $request->description,
            'is_default' => $request->boolean('is_default', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($roster, 201);
    }

    public function update(Request $request, Bands $band, Roster $roster): JsonResponse
    {
        $this->ensureRosterBelongsToBand($band, $roster);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $roster->update($validated);

        return response()->json($roster->fresh());
    }

    public function destroy(Bands $band, Roster $roster): JsonResponse
    {
        $this->ensureRosterBelongsToBand($band, $roster);

        if ($roster->is_default) {
            return response()->json(['message' => 'Cannot delete the default roster'], 422);
        }

        if ($roster->events()->exists()) {
            return response()->json(['message' => 'Cannot delete roster that is assigned to events'], 422);
        }

        $roster->delete();

        return response()->json(['message' => 'Roster deleted successfully']);
    }

    public function setDefault(Bands $band, Roster $roster): JsonResponse
    {
        $this->ensureRosterBelongsToBand($band, $roster);

        $roster->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default roster updated',
            'roster' => $roster->fresh(),
        ]);
    }

    public function initializeFromBand(Bands $band): JsonResponse
    {
        if ($band->defaultRoster) {
            return response()->json([
                'message' => 'Band already has a default roster',
                'roster' => $band->defaultRoster->load('members.user'),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $roster = Roster::createDefaultForBand($band);
            DB::commit();

            return response()->json([
                'message' => 'Default roster created successfully',
                'roster' => $roster->load('members.user'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create default roster',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show how this roster's current membership differs from the members on
     * its future events (people to remove, members missing from events).
     */
    public function futureEventsDiff(Bands $band, Roster $roster, RosterReconcileService $reconcile): JsonResponse
    {
        $this->ensureRosterBelongsToBand($band, $roster);

        return response()->json($reconcile->diffFutureEvents($roster));
    }

    /**
     * Apply selected add/remove actions to this roster's future events.
     */
    public function reconcileFutureEvents(Request $request, Bands $band, Roster $roster, RosterReconcileService $reconcile): JsonResponse
    {
        $this->ensureRosterBelongsToBand($band, $roster);

        $validated = $request->validate([
            'remove_member_ids' => ['array'],
            'remove_member_ids.*' => ['integer'],
            'add_member_ids' => ['array'],
            'add_member_ids.*' => ['integer'],
        ]);

        $result = $reconcile->applyReconcile(
            $roster,
            $validated['remove_member_ids'] ?? [],
            $validated['add_member_ids'] ?? [],
        );

        return response()->json([
            'message' => 'Future events updated',
            ...$result,
        ]);
    }

    private function ensureRosterBelongsToBand(Bands $band, Roster $roster): void
    {
        if ($roster->band_id !== $band->id) {
            abort(404, 'Roster does not belong to this band');
        }
    }
}
