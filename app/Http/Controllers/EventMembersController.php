<?php

namespace App\Http\Controllers;

use App\Models\Events;
use App\Models\EventMember;
use App\Models\Bands;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventMembersController extends Controller
{
    /**
     * Get all members for an event, including band members with their status.
     */
    public function index(Events $event)
    {
        $this->authorize('view', $event);

        $band = $event->eventable->band;

        // Get all band members (owners + members)
        $bandMembers = $band->everyone();

        // Get existing event members
        $eventMembers = $event->eventMembers()->with('user')->get();

        // Build comprehensive list
        $members = $bandMembers->map(function ($member) use ($eventMembers) {
            $eventMember = $eventMembers->firstWhere('user_id', $member->id);

            return [
                'id' => $eventMember?->id,
                'user_id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'status' => $eventMember?->status ?? 'playing',
                'is_band_member' => true,
                'payout_amount' => $eventMember?->payout_amount,
                'notes' => $eventMember?->notes,
            ];
        });

        // Add non-band member substitutes
        $substitutes = $eventMembers->where('is_band_member', false)->map(function ($sub) {
            return [
                'id' => $sub->id,
                'user_id' => $sub->user_id,
                'name' => $sub->display_name,
                'email' => $sub->display_email,
                'phone' => $sub->phone,
                'status' => $sub->status,
                'is_band_member' => false,
                'payout_amount' => $sub->payout_amount,
                'notes' => $sub->notes,
            ];
        });

        return response()->json([
            'members' => $members->values(),
            'substitutes' => $substitutes->values(),
        ]);
    }

    /**
     * Update the status of a band member for an event.
     */
    public function updateStatus(Request $request, Events $event, User $user)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['playing', 'absent'])],
            'payout_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $band = $event->eventable->band;

        // Verify user is a band member
        $isBandMember = $band->everyone()->contains('id', $user->id);
        if (!$isBandMember) {
            return response()->json(['message' => 'User is not a band member'], 403);
        }

        $eventMember = EventMember::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $user->id,
            ],
            [
                'band_id' => $band->id,
                'status' => $validated['status'],
                'is_band_member' => true,
                'payout_amount' => $validated['payout_amount'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Member status updated',
            'member' => $eventMember->load('user'),
        ]);
    }

    /**
     * Add a substitute to an event.
     */
    public function addSubstitute(Request $request, Events $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'name' => ['required_without:user_id', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'payout_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $band = $event->eventable->band;

        // If user_id is provided, check if they're already a band member
        $isBandMember = false;
        if (isset($validated['user_id'])) {
            $isBandMember = $band->everyone()->contains('id', $validated['user_id']);

            if ($isBandMember) {
                return response()->json([
                    'message' => 'This user is already a band member. Use the band members list instead.'
                ], 422);
            }
        }

        $eventMember = EventMember::create([
            'event_id' => $event->id,
            'band_id' => $band->id,
            'user_id' => $validated['user_id'] ?? null,
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'status' => 'substitute',
            'is_band_member' => false,
            'payout_amount' => $validated['payout_amount'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Substitute added',
            'member' => $eventMember->load('user'),
        ], 201);
    }

    /**
     * Update a substitute's information.
     */
    public function updateSubstitute(Request $request, Events $event, EventMember $eventMember)
    {
        $this->authorize('update', $event);

        if ($eventMember->event_id !== $event->id) {
            return response()->json(['message' => 'Event member not found'], 404);
        }

        if ($eventMember->is_band_member) {
            return response()->json(['message' => 'Cannot update band member as substitute'], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'payout_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $eventMember->update($validated);

        return response()->json([
            'message' => 'Substitute updated',
            'member' => $eventMember->fresh()->load('user'),
        ]);
    }

    /**
     * Remove a substitute from an event.
     */
    public function removeSubstitute(Events $event, EventMember $eventMember)
    {
        $this->authorize('update', $event);

        if ($eventMember->event_id !== $event->id) {
            return response()->json(['message' => 'Event member not found'], 404);
        }

        if ($eventMember->is_band_member) {
            return response()->json(['message' => 'Cannot remove band members, only substitutes'], 422);
        }

        $eventMember->delete();

        return response()->json(['message' => 'Substitute removed']);
    }

    /**
     * Initialize event members from band roster (for new events).
     */
    public function initializeFromBand(Events $event)
    {
        $this->authorize('update', $event);

        $band = $event->eventable->band;

        // Check if event already has members
        if ($event->eventMembers()->count() > 0) {
            return response()->json(['message' => 'Event already has members configured'], 422);
        }

        DB::beginTransaction();
        try {
            $bandMembers = $band->everyone();

            foreach ($bandMembers as $member) {
                EventMember::create([
                    'event_id' => $event->id,
                    'band_id' => $band->id,
                    'user_id' => $member->id,
                    'status' => 'playing',
                    'is_band_member' => true,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Event members initialized from band roster',
                'count' => $bandMembers->count(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
