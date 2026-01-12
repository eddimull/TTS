<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\Roster;
use App\Http\Requests\StoreRosterRequest;
use App\Http\Requests\UpdateRosterRequest;
use Illuminate\Support\Facades\DB;

class RosterController extends Controller
{
    /**
     * Get all rosters for a band.
     */
    public function index(Bands $band)
    {
        // Check if user can view band rosters
        if (!$band->everyone()->contains('user_id', auth()->id())) {
            abort(403, 'Unauthorized');
        }

        $rosters = $band->rosters()
            ->withCount('members')
            ->with(['members' => function ($query) {
                $query->where('is_active', true)->with('user');
            }])
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // If this is a JSON API request (from EventEditor), return JSON
        // Note: Don't use request()->ajax() as Inertia also sends X-Requested-With header
        if (request()->wantsJson()) {
            return response()->json([
                'rosters' => $rosters,
            ]);
        }

        // Get all roster members for the band (for call lists)
        $rosterMembers = $band->rosters()
            ->with('members.user')
            ->get()
            ->pluck('members')
            ->flatten()
            ->unique('id')
            ->filter(fn($member) => $member->is_active)
            ->values()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'display_name' => $member->display_name,
                    'display_email' => $member->display_email,
                    'phone' => $member->phone,
                    'role' => $member->role,
                ];
            });

        // Otherwise return Inertia view (for the Rosters page)
        return inertia('Band/Rosters/Index', [
            'band' => $band,
            'rosters' => $rosters,
            'rosterMembers' => $rosterMembers,
        ]);
    }

    /**
     * Show a single roster with all its members.
     */
    public function show(Roster $roster)
    {
        // Check if user can view this roster
        if (!$roster->band->everyone()->contains('user_id', auth()->id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $roster->load(['members.user', 'band']);

        return response()->json([
            'roster' => $roster,
            'members' => $roster->members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'name' => $member->display_name,
                    'email' => $member->display_email,
                    'phone' => $member->phone,
                    'role' => $member->role,
                    'default_payout_type' => $member->default_payout_type,
                    'default_payout_amount' => $member->default_payout_amount ? $member->default_payout_amount / 100 : null,
                    'notes' => $member->notes,
                    'is_active' => $member->is_active,
                    'is_user' => $member->isUser(),
                ];
            }),
        ]);
    }

    /**
     * Store a newly created roster.
     */
    public function store(StoreRosterRequest $request, Bands $band)
    {
        $roster = Roster::create([
            'band_id' => $band->id,
            'name' => $request->name,
            'description' => $request->description,
            'is_default' => $request->boolean('is_default', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Roster created successfully');
    }

    /**
     * Update the specified roster.
     */
    public function update(UpdateRosterRequest $request, Roster $roster)
    {
        $roster->update($request->validated());

        return back()->with('success', 'Roster updated successfully');
    }

    /**
     * Remove the specified roster.
     */
    public function destroy(Roster $roster)
    {
        // Check authorization - only owners
        if (!$roster->band->owners()->where('user_id', auth()->id())->exists()) {
            abort(403, 'Unauthorized');
        }

        // Don't allow deleting default roster
        if ($roster->is_default) {
            return back()->withErrors(['message' => 'Cannot delete the default roster']);
        }

        // Check if roster is being used by any events
        if ($roster->events()->count() > 0) {
            return back()->withErrors([
                'message' => 'Cannot delete roster that is assigned to events'
            ]);
        }

        $roster->delete();

        return back()->with('success', 'Roster deleted successfully');
    }

    /**
     * Set a roster as the default for the band.
     */
    public function setDefault(Bands $band, Roster $roster)
    {
        // Check authorization - only owners
        if (!$band->owners()->where('user_id', auth()->id())->exists()) {
            abort(403, 'Unauthorized');
        }

        if ($roster->band_id !== $band->id) {
            abort(404, 'Roster does not belong to this band');
        }

        $roster->is_default = true;
        $roster->save();

        return back()->with('success', 'Default roster updated');
    }

    /**
     * Initialize roster from band members.
     */
    public function initializeFromBand(Bands $band)
    {
        // Check authorization - only owners
        if (!$band->owners()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if band already has a default roster
        if ($band->defaultRoster) {
            return response()->json([
                'message' => 'Band already has a default roster',
                'roster' => $band->defaultRoster->load('members.user'),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $roster = Roster::createDefaultForBand($band);

            DB::commit();

            return response()->json([
                'message' => 'Default roster created successfully',
                'roster' => $roster->load('members.user'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create default roster',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

