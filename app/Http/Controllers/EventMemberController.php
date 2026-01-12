<?php

namespace App\Http\Controllers;

use App\Models\Events;
use App\Models\EventMember;
use Illuminate\Http\Request;

class EventMemberController extends Controller
{
    /**
     * Get all members for an event.
     */
    public function index(Events $event)
    {
        $members = $event->eventMembers()->with(['rosterMember', 'user'])->get();

        $formattedMembers = $members->map(function ($member) {
            return [
                'id' => $member->id,
                'roster_member_id' => $member->roster_member_id,
                'user_id' => $member->user_id,
                'display_name' => $member->display_name, // Uses model accessor
                'role' => $member->role ?? $member->rosterMember?->role, // Custom role or roster member role
                'email' => $member->display_email, // Uses model accessor
                'attendance_status' => $member->attendance_status,
            ];
        });

        return response()->json([
            'members' => $formattedMembers
        ]);
    }

    /**
     * Add a member to an event.
     */
    public function store(Request $request, Events $event)
    {
        $validated = $request->validate([
            'roster_member_id' => 'nullable|exists:roster_members,id',
            'user_id' => 'nullable|exists:users,id',
            'name' => 'required_without:roster_member_id|string|max:255',
            'role' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'attendance_status' => 'sometimes|in:confirmed,attended,absent,excused',
        ]);

        // Get band_id from event's eventable
        $bandId = $event->eventable->band_id ?? null;

        if (!$bandId) {
            return response()->json([
                'message' => 'Cannot determine band for this event'
            ], 400);
        }

        $eventMember = EventMember::create([
            'event_id' => $event->id,
            'band_id' => $bandId,
            'roster_member_id' => $validated['roster_member_id'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'role' => $validated['role'] ?? null,
            'attendance_status' => $validated['attendance_status'] ?? 'confirmed',
        ]);

        return response()->json([
            'message' => 'Member added to event successfully',
            'member' => $eventMember->load(['rosterMember', 'user']),
        ], 201);
    }

    /**
     * Update event member attendance.
     */
    public function update(Request $request, EventMember $eventMember)
    {
        $validated = $request->validate([
            'attendance_status' => 'sometimes|in:confirmed,attended,absent,excused',
            'payout_amount' => 'nullable|numeric|min:0',
        ]);

        // Convert payout amount to cents if provided
        if (isset($validated['payout_amount'])) {
            $validated['payout_amount'] = (int) ($validated['payout_amount'] * 100);
        }

        $eventMember->update($validated);

        return response()->json([
            'message' => 'Event member updated successfully',
            'member' => $eventMember->fresh()->load(['rosterMember', 'user']),
        ]);
    }

    /**
     * Remove a member from an event.
     */
    public function destroy(EventMember $eventMember)
    {
        $eventMember->delete();

        return response()->json([
            'message' => 'Member removed from event successfully'
        ]);
    }

    /**
     * Update event's roster and sync members from roster template.
     */
    public function updateRoster(Request $request, Events $event)
    {
        $validated = $request->validate([
            'roster_id' => 'nullable|exists:rosters,id',
        ]);

        // Update the event's roster_id
        $event->roster_id = $validated['roster_id'];
        $event->save();

        // The model observer will automatically sync roster members
        // when roster_id changes (see Events::booted() method)

        return response()->json([
            'message' => 'Roster applied successfully',
            'event' => $event->fresh(),
        ]);
    }
}
