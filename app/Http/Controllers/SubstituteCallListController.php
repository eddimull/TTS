<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\SubstituteCallList;
use Illuminate\Http\Request;

class SubstituteCallListController extends Controller
{
    /**
     * Get call list for a band, optionally filtered by instrument.
     */
    public function index(Bands $band, Request $request)
    {
        $query = $band->substituteCallLists()->with('rosterMember.user');

        if ($request->has('instrument')) {
            $query->where('instrument', $request->instrument);
        }

        $callLists = $query->get()->map(function ($entry) {
            $data = [
                'id' => $entry->id,
                'band_id' => $entry->band_id,
                'instrument' => $entry->instrument,
                'roster_member_id' => $entry->roster_member_id,
                'custom_name' => $entry->custom_name,
                'custom_email' => $entry->custom_email,
                'custom_phone' => $entry->custom_phone,
                'priority' => $entry->priority,
                'notes' => $entry->notes,
            ];

            // Include roster member data if exists
            if ($entry->rosterMember) {
                $data['roster_member'] = [
                    'id' => $entry->rosterMember->id,
                    'user_id' => $entry->rosterMember->user_id,
                    'display_name' => $entry->rosterMember->display_name,
                    'display_email' => $entry->rosterMember->display_email,
                    'phone' => $entry->rosterMember->phone,
                    'role' => $entry->rosterMember->role,
                ];
            }

            return $data;
        })->groupBy('instrument');

        return response()->json([
            'call_lists' => $callLists,
            'instruments' => $callLists->keys(),
        ]);
    }

    /**
     * Store a new substitute in the call list.
     */
    public function store(Request $request, Bands $band)
    {
        $validated = $request->validate([
            'instrument' => 'required|string|max:255',
            'roster_member_id' => 'nullable|exists:roster_members,id',
            'custom_name' => 'required_without:roster_member_id|string|max:255',
            'custom_email' => 'nullable|email|max:255',
            'custom_phone' => 'nullable|string|max:50',
            'priority' => 'sometimes|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        // Auto-assign priority if not provided
        if (!isset($validated['priority'])) {
            $maxPriority = $band->substituteCallLists()
                ->where('instrument', $validated['instrument'])
                ->max('priority') ?? 0;
            $validated['priority'] = $maxPriority + 1;
        }

        $validated['band_id'] = $band->id;

        $callListEntry = SubstituteCallList::create($validated);

        return response()->json([
            'message' => 'Substitute added to call list',
            'entry' => $callListEntry->load('rosterMember.user'),
        ], 201);
    }

    /**
     * Update a call list entry.
     */
    public function update(Request $request, SubstituteCallList $substituteCallList)
    {
        $validated = $request->validate([
            'priority' => 'sometimes|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $substituteCallList->update($validated);

        return response()->json([
            'message' => 'Call list entry updated',
            'entry' => $substituteCallList->fresh()->load('rosterMember.user'),
        ]);
    }

    /**
     * Delete a call list entry.
     */
    public function destroy(SubstituteCallList $substituteCallList)
    {
        $substituteCallList->delete();

        return response()->json([
            'message' => 'Substitute removed from call list',
        ]);
    }

    /**
     * Reorder call list for an instrument.
     */
    public function reorder(Request $request, Bands $band)
    {
        $validated = $request->validate([
            'instrument' => 'required|string',
            'order' => 'required|array',
            'order.*' => 'exists:substitute_call_lists,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            SubstituteCallList::where('id', $id)
                ->where('band_id', $band->id)
                ->update(['priority' => $index + 1]);
        }

        return response()->json([
            'message' => 'Call list reordered successfully',
        ]);
    }
}
