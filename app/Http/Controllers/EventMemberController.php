<?php

namespace App\Http\Controllers;

use App\Models\Events;
use App\Models\EventMember;
use App\Models\User;
use App\Services\SubInvitationService;
use Illuminate\Http\Request;

class EventMemberController extends Controller
{
    /**
     * Get all members for an event.
     */
    public function index(Events $event)
    {
        $members = $event->eventMembers()
            ->with(['rosterMember.bandRole', 'bandRole', 'user'])
            ->get();

        $formattedMembers = $members->map(function ($member) {
            return [
                'id' => $member->id,
                'roster_member_id' => $member->roster_member_id,
                'user_id' => $member->user_id,
                'display_name' => $member->display_name, // Uses model accessor
                'role' => $member->role_name, // Uses accessor that resolves BandRole
                'band_role_id' => $member->band_role_id, // Include for dropdown selection
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
            'name' => 'required_without:roster_member_id,user_id|string|max:255',
            'role' => 'nullable|string|max:100',
            'band_role_id' => 'nullable|exists:band_roles,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'attendance_status' => 'sometimes|in:confirmed,attended,absent,excused',
            'invite_substitute' => 'sometimes|boolean',
        ]);

        // Get band_id from event's eventable
        $bandId = $event->eventable->band_id ?? null;

        if (!$bandId) {
            return response()->json([
                'message' => 'Cannot determine band for this event'
            ], 400);
        }

        $userId = $validated['user_id'] ?? null;
        $invitedNewUser = false;

        // Handle substitute invitation if requested and email provided
        if ($validated['invite_substitute'] ?? false) {
            if (!isset($validated['email']) || empty($validated['email'])) {
                return response()->json([
                    'message' => 'Email is required to invite a substitute'
                ], 422);
            }

            // Use the SubInvitationService to send proper sub invitation
            $subInvitationService = new SubInvitationService();
            $invitedNewUser = true;

            try {
                // Create the event_subs invitation record
                $eventSub = $subInvitationService->inviteSubToEvent(
                    eventId: $event->id,
                    bandId: $bandId,
                    email: $validated['email'],
                    name: $validated['name'] ?? null,
                    phone: $validated['phone'] ?? null,
                    bandRoleId: $validated['band_role_id'] ?? null,
                    payoutAmount: null, // Can be set later
                    notes: null
                );

                // If user exists, they'll be linked automatically by SubInvitationService
                $existingUser = User::where('email', $validated['email'])->first();
                if ($existingUser) {
                    $userId = $existingUser->id;
                    $invitedNewUser = false;
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send sub invitation', [
                    'email' => $validated['email'],
                    'event_id' => $event->id,
                    'band_id' => $bandId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $eventMember = EventMember::create([
            'event_id' => $event->id,
            'band_id' => $bandId,
            'roster_member_id' => $validated['roster_member_id'] ?? null,
            'user_id' => $userId,
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'] ?? null,
            'band_role_id' => $validated['band_role_id'] ?? null,
            'attendance_status' => $validated['attendance_status'] ?? 'confirmed',
        ]);

        return response()->json([
            'message' => 'Member added to event successfully',
            'member' => $eventMember->load(['rosterMember', 'user']),
            'invited' => $invitedNewUser,
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
