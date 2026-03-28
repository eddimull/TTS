<?php

namespace App\Http\Controllers;

use App\Models\Roster;
use App\Models\RosterSlot;
use App\Http\Requests\StoreRosterSlotRequest;
use App\Http\Requests\UpdateRosterSlotRequest;
use Illuminate\Http\JsonResponse;

class RosterSlotController extends Controller
{
    public function index(Roster $roster): JsonResponse
    {
        if (!$roster->band->everyone()->contains('user_id', auth()->id())) {
            abort(403, 'Unauthorized');
        }

        $slots = $roster->slots()
            ->with(['bandRole', 'activeMembers.user', 'activeMembers.bandRole'])
            ->get();

        return response()->json(['slots' => $slots]);
    }

    public function store(StoreRosterSlotRequest $request, Roster $roster): JsonResponse
    {
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

    public function update(UpdateRosterSlotRequest $request, RosterSlot $rosterSlot): JsonResponse
    {
        $rosterSlot->update($request->validated());

        return response()->json([
            'message' => 'Slot updated successfully',
            'slot'    => $rosterSlot->fresh()->load(['bandRole', 'activeMembers']),
        ]);
    }

    public function destroy(RosterSlot $rosterSlot): JsonResponse
    {
        if (!$rosterSlot->roster->band->owners()->where('user_id', auth()->id())->exists()) {
            abort(403, 'Unauthorized');
        }

        $rosterSlot->delete();

        return response()->json(['message' => 'Slot deleted successfully']);
    }
}
