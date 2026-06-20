<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Roster;
use App\Models\RosterSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile equivalent of {@see \App\Http\Controllers\RosterSlotController}.
 *
 * Band ownership is enforced by the `owner` middleware on the route group.
 * Bound {roster}/{rosterSlot} are verified to belong to the {band} (404 if not).
 */
class RosterSlotsController extends Controller
{
    public function index(Bands $band, Roster $roster): JsonResponse
    {
        $this->ensureRosterBelongsToBand($band, $roster);

        $slots = $roster->slots()
            ->with(['bandRole', 'activeMembers.user', 'activeMembers.bandRole'])
            ->get();

        return response()->json(['slots' => $slots]);
    }

    public function store(Request $request, Bands $band, Roster $roster): JsonResponse
    {
        $this->ensureRosterBelongsToBand($band, $roster);

        $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'band_role_id' => ['nullable', 'exists:band_roles,id'],
            'is_required'  => ['boolean'],
            'quantity'     => ['integer', 'min:1', 'max:99'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $slot = RosterSlot::create([
            'roster_id'    => $roster->id,
            'band_role_id' => $request->band_role_id,
            'name'         => $request->name,
            'is_required'  => $request->boolean('is_required', true),
            'quantity'     => $request->integer('quantity', 1),
            'notes'        => $request->notes,
        ]);

        return response()->json([
            'message' => 'Slot created successfully',
            'slot'    => $slot->load(['bandRole', 'activeMembers']),
        ], 201);
    }

    public function update(Request $request, Bands $band, RosterSlot $rosterSlot): JsonResponse
    {
        $this->ensureSlotBelongsToBand($band, $rosterSlot);

        $validated = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'band_role_id' => ['nullable', 'exists:band_roles,id'],
            'is_required'  => ['boolean'],
            'quantity'     => ['integer', 'min:1', 'max:99'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $rosterSlot->update($validated);

        return response()->json([
            'message' => 'Slot updated successfully',
            'slot'    => $rosterSlot->fresh()->load(['bandRole', 'activeMembers']),
        ]);
    }

    public function destroy(Bands $band, RosterSlot $rosterSlot): JsonResponse
    {
        $this->ensureSlotBelongsToBand($band, $rosterSlot);

        $rosterSlot->delete();

        return response()->json(['message' => 'Slot deleted successfully']);
    }

    private function ensureRosterBelongsToBand(Bands $band, Roster $roster): void
    {
        if ($roster->band_id !== $band->id) {
            abort(404, 'Roster does not belong to this band');
        }
    }

    private function ensureSlotBelongsToBand(Bands $band, RosterSlot $rosterSlot): void
    {
        if ($rosterSlot->roster->band_id !== $band->id) {
            abort(404, 'Slot does not belong to this band');
        }
    }
}
