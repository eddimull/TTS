<?php

namespace App\Http\Controllers;

use App\Models\EventSubs;
use App\Services\SubInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubInvitationController extends Controller
{
    protected $subInvitationService;

    public function __construct(SubInvitationService $subInvitationService)
    {
        $this->subInvitationService = $subInvitationService;
    }

    /**
     * Show the invitation page
     */
    public function show(string $key)
    {
        $eventSub = EventSubs::where('invitation_key', $key)->firstOrFail();

        // Load relationships including band role
        $eventSub->load(['event.eventable', 'band', 'bandRole']);

        // If user is already authenticated
        if (Auth::check()) {
            // If invitation is still pending, accept it
            if ($eventSub->pending) {
                $this->subInvitationService->acceptInvitation($key, Auth::user());
            }

            // Redirect to dashboard where they can see their events
            return redirect()->route('dashboard')
                ->with('success', 'Invitation accepted! You can now view the event details.');
        }

        // Extract charts/songs from event's additional_data
        $event = $eventSub->event;
        $charts = [];
        $songs = [];
        $startTime = null;
        $endTime = null;

        if ($event->additional_data) {
            $additionalData = is_string($event->additional_data)
                ? json_decode($event->additional_data)
                : (object) $event->additional_data;

            // Check for booking events (nested in performance object)
            if (isset($additionalData->performance)) {
                $charts = $additionalData->performance->charts ?? [];
                $songs = $additionalData->performance->songs ?? [];
            }
            // Check for rehearsal events (directly in additional_data)
            else {
                $charts = $additionalData->charts ?? [];
                $songs = $additionalData->songs ?? [];
            }

            // Extract end time from timeline
            if (isset($additionalData->times) && is_array($additionalData->times) && count($additionalData->times) > 0) {
                $times = collect($additionalData->times)->sortBy('time');
                $endTime = \Carbon\Carbon::parse($times->last()->time)->format('H:i:s');
            }
        }

        // Use event's time field as start time (performance start, not load in)
        if ($event->time) {
            $startTime = is_string($event->time) ? $event->time : $event->time->format('H:i:s');
        }

        // Convert charts/songs to arrays if they're collections
        if ($charts instanceof \Illuminate\Support\Collection) {
            $charts = $charts->toArray();
        }
        if ($songs instanceof \Illuminate\Support\Collection) {
            $songs = $songs->toArray();
        }

        // Build event data with start/end times
        $eventData = $event->toArray();
        $eventData['start_time'] = $startTime;
        $eventData['end_time'] = $endTime;

        // Show invitation page for non-authenticated users
        return Inertia::render('SubInvitation/Show', [
            'eventSub' => $eventSub,
            'event' => $eventData,
            'band' => $eventSub->band,
            'invitationKey' => $key,
            'charts' => $charts,
            'songs' => $songs,
            'roleName' => $eventSub->role_name,
        ]);
    }

    /**
     * Accept the invitation (for authenticated users)
     */
    public function accept(string $key)
    {
        $eventSub = $this->subInvitationService->acceptInvitation($key, Auth::user());

        return redirect()
            ->route('dashboard')
            ->with('success', 'Invitation accepted! You can now view the event details.');
    }

    /**
     * Add a sub to an event (for band owners/members)
     */
    public function store(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'band_id' => 'required|exists:bands,id',
            'email' => 'required|email',
            'name' => 'nullable|string',
            'phone' => 'nullable|string',
            'band_role_id' => 'nullable|exists:band_roles,id',
            'payout_amount' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        // TODO: Add authorization check to ensure user can add subs to this band

        $eventSub = $this->subInvitationService->inviteSubToEvent(
            $request->event_id,
            $request->band_id,
            $request->email,
            $request->name,
            $request->phone,
            $request->band_role_id,
            $request->payout_amount,
            $request->notes
        );

        return response()->json([
            'message' => 'Substitute invited successfully!',
            'eventSub' => $eventSub,
        ], 201);
    }

    /**
     * Remove a sub from an event
     */
    public function destroy(int $eventSubId)
    {
        // TODO: Add authorization check

        $this->subInvitationService->removeSubFromEvent($eventSubId);

        return response()->json([
            'message' => 'Substitute removed successfully!',
        ]);
    }

    /**
     * Get all pending invitations for the authenticated user
     */
    public function myInvitations()
    {
        $invitations = $this->subInvitationService->getPendingInvitationsForUser(Auth::user());

        return response()->json(['invitations' => $invitations]);
    }
}
