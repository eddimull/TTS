<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\Roster;
use App\Http\Requests\StoreRosterRequest;
use App\Http\Requests\UpdateRosterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Response as InertiaResponse;

class RosterController extends Controller
{
    /**
     * Get all rosters for a band.
     */
    public function index(Bands $band): JsonResponse|InertiaResponse
    {
        $this->authorizeViewBand($band);

        $rosters = $band->rosters()
            ->withCount('members')
            ->with(['members' => fn($query) => $query->where('is_active', true)->with('user')])
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        if (request()->wantsJson()) {
            return response()->json(['rosters' => $rosters]);
        }

        $rosterMembers = $this->getActiveRosterMembers($band);

        return inertia('Band/Rosters/Index', [
            'band' => $band,
            'rosters' => $rosters,
            'rosterMembers' => $rosterMembers,
        ]);
    }

    /**
     * Show a single roster with all its members.
     */
    public function show(Roster $roster): JsonResponse
    {
        $this->authorizeViewBand($roster->band);

        $roster->load(['members.user', 'band']);

        return response()->json([
            'roster' => $roster,
            'members' => $roster->members->map(fn($member) => [
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
            ]),
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

        return $this->respondWithSuccess($roster, 'Roster created successfully', 201);
    }

    /**
     * Update the specified roster.
     */
    public function update(UpdateRosterRequest $request, Roster $roster)
    {
        $roster->update($request->validated());

        return $this->respondWithSuccess($roster, 'Roster updated successfully');
    }

    /**
     * Remove the specified roster.
     */
    public function destroy(Roster $roster)
    {
        $this->authorizeBandOwner($roster->band);

        if ($roster->is_default) {
            return $this->respondWithError('Cannot delete the default roster', 422);
        }

        if ($roster->events()->exists()) {
            return $this->respondWithError('Cannot delete roster that is assigned to events', 422);
        }

        $roster->delete();

        return $this->respondWithSuccess(['message' => 'Roster deleted successfully'], 'Roster deleted successfully');
    }

    /**
     * Set a roster as the default for the band.
     */
    public function setDefault(Bands $band, Roster $roster)
    {
        $this->authorizeBandOwner($band);

        if ($roster->band_id !== $band->id) {
            abort(404, 'Roster does not belong to this band');
        }

        $roster->update(['is_default' => true]);

        return $this->respondWithSuccess(
            ['message' => 'Default roster updated', 'roster' => $roster->fresh()],
            'Default roster updated'
        );
    }

    /**
     * Initialize roster from band members.
     */
    public function initializeFromBand(Bands $band): JsonResponse
    {
        $this->authorizeBandOwner($band);

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

    /**
     * Authorize that the current user can view this band.
     */
    private function authorizeViewBand(Bands $band): void
    {
        if (!$band->everyone()->contains('user_id', auth()->id())) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Authorize that the current user is an owner of this band.
     */
    private function authorizeBandOwner(Bands $band): void
    {
        if (!$band->owners()->where('user_id', auth()->id())->exists()) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Get all active roster members for a band.
     */
    private function getActiveRosterMembers(Bands $band)
    {
        return $band->rosters()
            ->with('members.user')
            ->get()
            ->pluck('members')
            ->flatten()
            ->unique('id')
            ->filter(fn($member) => $member->is_active)
            ->values()
            ->map(fn($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'display_name' => $member->display_name,
                'display_email' => $member->display_email,
                'phone' => $member->phone,
                'role' => $member->role,
            ]);
    }

    /**
     * Return a success response (JSON or redirect back with message).
     */
    private function respondWithSuccess($data, string $message, int $status = 200)
    {
        if (request()->wantsJson()) {
            return response()->json($data, $status);
        }

        return back()->with('success', $message);
    }

    /**
     * Return an error response (JSON or redirect back with error).
     */
    private function respondWithError(string $message, int $status = 422)
    {
        if (request()->wantsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->withErrors(['message' => $message]);
    }
}

