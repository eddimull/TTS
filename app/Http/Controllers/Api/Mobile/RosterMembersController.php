<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Services\RosterReconcileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mobile equivalent of {@see \App\Http\Controllers\RosterMemberController}.
 *
 * Band ownership is enforced by the `owner` middleware on the route group.
 * Bound {roster}/{rosterMember} are verified to belong to the {band} (404 if not).
 */
class RosterMembersController extends Controller
{
    public function __construct(private RosterReconcileService $reconcile)
    {
    }

    public function store(Request $request, Bands $band, Roster $roster): JsonResponse
    {
        $this->ensureRosterBelongsToBand($band, $roster);

        $request->validate([
            'user_id' => [
                'nullable',
                'exists:users,id',
                Rule::unique('roster_members')->where(function ($query) use ($roster) {
                    return $query->where('roster_id', $roster->id)->whereNull('deleted_at');
                }),
            ],
            'name' => ['required_without:user_id', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:100'],
            'band_role_id' => ['nullable', 'exists:band_roles,id'],
            'slot_id' => ['nullable', Rule::exists('roster_slots', 'id')->where('roster_id', $roster->id)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'apply_to_future_events' => ['boolean'],
        ]);

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

    public function update(Request $request, Bands $band, RosterMember $rosterMember): JsonResponse
    {
        $this->ensureMemberBelongsToBand($band, $rosterMember);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:100'],
            'band_role_id' => ['nullable', 'exists:band_roles,id'],
            // Scope the slot to this member's own roster so a member can't be
            // attached to a slot from another roster/band.
            'slot_id' => [
                'nullable',
                Rule::exists('roster_slots', 'id')
                    ->where('roster_id', $rosterMember->roster_id),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $rosterMember->update($validated);

        return response()->json([
            'message' => 'Roster member updated successfully',
            'member' => $rosterMember->fresh()->load(['user', 'bandRole']),
        ]);
    }

    public function destroy(Request $request, Bands $band, RosterMember $rosterMember): JsonResponse
    {
        $this->ensureMemberBelongsToBand($band, $rosterMember);

        // Remove from future events before deleting, while the member's
        // identity (roster_member_id / user_id) is still available.
        $futureEventsAffected = $request->boolean('apply_to_future_events')
            ? $this->reconcile->removeMemberFromFutureEvents($rosterMember)
            : 0;

        $attendanceCount = $rosterMember->eventAttendance()->count();

        if ($attendanceCount > 0) {
            // Soft delete to preserve history.
            $rosterMember->delete();

            return response()->json([
                'message' => 'Roster member removed (archived due to attendance history)',
                'attendance_count' => $attendanceCount,
                'future_events_affected' => $futureEventsAffected,
            ]);
        }

        // Force delete if no history.
        $rosterMember->forceDelete();

        return response()->json([
            'message' => 'Roster member removed successfully',
            'future_events_affected' => $futureEventsAffected,
        ]);
    }

    public function toggleActive(Bands $band, RosterMember $rosterMember): JsonResponse
    {
        $this->ensureMemberBelongsToBand($band, $rosterMember);

        $rosterMember->is_active = !$rosterMember->is_active;
        $rosterMember->save();

        return response()->json([
            'message' => $rosterMember->is_active ? 'Member activated' : 'Member deactivated',
            'member' => $rosterMember->fresh()->load(['user', 'bandRole']),
        ]);
    }

    private function ensureRosterBelongsToBand(Bands $band, Roster $roster): void
    {
        if ($roster->band_id !== $band->id) {
            abort(404, 'Roster does not belong to this band');
        }
    }

    private function ensureMemberBelongsToBand(Bands $band, RosterMember $rosterMember): void
    {
        if ($rosterMember->roster->band_id !== $band->id) {
            abort(404, 'Roster member does not belong to this band');
        }
    }
}
