<?php

namespace App\Http\Controllers;

use App\Models\Roster;
use App\Models\RosterMember;
use App\Http\Requests\StoreRosterMemberRequest;
use App\Http\Requests\UpdateRosterMemberRequest;
use App\Services\RosterReconcileService;
use Illuminate\Http\Request;

class RosterMemberController extends Controller
{
    public function __construct(private RosterReconcileService $reconcile)
    {
    }

    /**
     * Add a member to a roster.
     */
    public function store(StoreRosterMemberRequest $request, Roster $roster)
    {
        $attributes = [
            'slot_id' => $request->slot_id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => $request->role,
            'band_role_id' => $request->band_role_id,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', true),
            'deleted_at' => null, // Restore if soft-deleted
        ];

        if ($request->filled('user_id')) {
            // A real user maps to one row per roster — upsert (and restore a
            // soft-deleted row) on (roster_id, user_id).
            $member = RosterMember::withTrashed()->updateOrCreate(
                ['roster_id' => $roster->id, 'user_id' => $request->user_id],
                $attributes,
            );
        } else {
            // Custom (non-user) people have no natural key, so each add is a
            // new row. updateOrCreate on a NULL user_id would collapse them all
            // onto one record.
            $member = RosterMember::create([
                'roster_id' => $roster->id,
                'user_id' => null,
                ...$attributes,
            ]);
        }

        $futureEventsAffected = $request->boolean('apply_to_future_events')
            ? $this->reconcile->addMemberToFutureEvents($member)
            : 0;

        return response()->json([
            'message' => 'Member added to roster successfully',
            'member' => $member->load(['user', 'bandRole']),
            'future_events_affected' => $futureEventsAffected,
        ], 201);
    }

    /**
     * Update a roster member.
     */
    public function update(UpdateRosterMemberRequest $request, RosterMember $rosterMember)
    {
        $rosterMember->update($request->validated());

        return response()->json([
            'message' => 'Roster member updated successfully',
            'member' => $rosterMember->fresh()->load(['user', 'bandRole']),
        ]);
    }

    /**
     * Remove a member from a roster.
     */
    public function destroy(Request $request, RosterMember $rosterMember)
    {
        // Check authorization - only owners
        if (!$rosterMember->roster->band->owners()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Remove from future events before deleting, while the member's
        // identity (roster_member_id / user_id) is still available.
        $futureEventsAffected = $request->boolean('apply_to_future_events')
            ? $this->reconcile->removeMemberFromFutureEvents($rosterMember)
            : 0;

        // Check if member has attendance records
        $attendanceCount = $rosterMember->eventAttendance()->count();

        if ($attendanceCount > 0) {
            // Soft delete to preserve history
            $rosterMember->delete();

            return response()->json([
                'message' => 'Roster member removed (archived due to attendance history)',
                'attendance_count' => $attendanceCount,
                'future_events_affected' => $futureEventsAffected,
            ]);
        }

        // Force delete if no history
        $rosterMember->forceDelete();

        return response()->json([
            'message' => 'Roster member removed successfully',
            'future_events_affected' => $futureEventsAffected,
        ]);
    }

    /**
     * Toggle active status of a roster member.
     */
    public function toggleActive(RosterMember $rosterMember)
    {
        // Check authorization - only owners
        if (!$rosterMember->roster->band->owners()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rosterMember->is_active = !$rosterMember->is_active;
        $rosterMember->save();

        return response()->json([
            'message' => $rosterMember->is_active ? 'Member activated' : 'Member deactivated',
            'member' => $rosterMember->fresh()->load(['user', 'bandRole']),
        ]);
    }
}
