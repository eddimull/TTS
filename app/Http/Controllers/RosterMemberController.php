<?php

namespace App\Http\Controllers;

use App\Models\Roster;
use App\Models\RosterMember;
use App\Http\Requests\StoreRosterMemberRequest;
use App\Http\Requests\UpdateRosterMemberRequest;

class RosterMemberController extends Controller
{
    /**
     * Add a member to a roster.
     */
    public function store(StoreRosterMemberRequest $request, Roster $roster)
    {
        $member = RosterMember::create([
            'roster_id' => $roster->id,
            'user_id' => $request->user_id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => $request->role,
            'default_payout_type' => $request->default_payout_type ?? 'equal_split',
            'default_payout_amount' => $request->default_payout_amount,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Member added to roster successfully',
            'member' => $member->load('user'),
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
            'member' => $rosterMember->fresh()->load('user'),
        ]);
    }

    /**
     * Remove a member from a roster.
     */
    public function destroy(RosterMember $rosterMember)
    {
        // Check authorization - only owners
        if (!$rosterMember->roster->band->owners->contains('user_id', auth()->id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if member has attendance records
        $attendanceCount = $rosterMember->eventAttendance()->count();

        if ($attendanceCount > 0) {
            // Soft delete to preserve history
            $rosterMember->delete();

            return response()->json([
                'message' => 'Roster member removed (archived due to attendance history)',
                'attendance_count' => $attendanceCount,
            ]);
        }

        // Force delete if no history
        $rosterMember->forceDelete();

        return response()->json([
            'message' => 'Roster member removed successfully'
        ]);
    }

    /**
     * Toggle active status of a roster member.
     */
    public function toggleActive(RosterMember $rosterMember)
    {
        // Check authorization - only owners
        if (!$rosterMember->roster->band->owners->contains('user_id', auth()->id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rosterMember->is_active = !$rosterMember->is_active;
        $rosterMember->save();

        return response()->json([
            'message' => $rosterMember->is_active ? 'Member activated' : 'Member deactivated',
            'member' => $rosterMember->fresh()->load('user'),
        ]);
    }
}
