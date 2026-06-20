<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\BandSubs;
use App\Models\RosterMember;
use App\Models\SubstituteCallList;
use App\Services\SubInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile equivalent of {@see \App\Http\Controllers\SubstituteCallListController}.
 *
 * Band ownership is enforced by the `owner` middleware on the route group, so
 * no in-controller authorize() call is needed — we only confirm that the bound
 * {callList} entry actually belongs to the {band} (returning 404 if not). The
 * belongs-to-band guard is done inline so it runs before any other check.
 */
class SubstituteCallListsController extends Controller
{
    /**
     * GET /api/mobile/bands/{band}/call-lists
     *
     * Grouped-by-instrument call lists. Mirrors the web index shape.
     */
    public function index(Bands $band, Request $request): JsonResponse
    {
        $query = $band->substituteCallLists()->with(['rosterMember.user', 'bandRole']);

        if ($request->filled('instrument')) {
            $query->where('instrument', $request->input('instrument'));
        }

        $callLists = $query->orderBy('priority')->get()->map(function ($entry) {
            $data = [
                'id' => $entry->id,
                'band_id' => $entry->band_id,
                'instrument' => $entry->instrument,
                'band_role_id' => $entry->band_role_id,
                'roster_member_id' => $entry->roster_member_id,
                'custom_name' => $entry->custom_name,
                'custom_email' => $entry->custom_email,
                'custom_phone' => $entry->custom_phone,
                'priority' => $entry->priority,
                'notes' => $entry->notes,
            ];

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
     * POST /api/mobile/bands/{band}/call-lists
     */
    public function store(Request $request, Bands $band, SubInvitationService $subInvitationService): JsonResponse
    {
        $validated = $request->validate([
            'instrument' => 'nullable|string|max:255',
            'band_role_id' => 'nullable|exists:band_roles,id',
            'roster_member_id' => 'nullable|exists:roster_members,id',
            'custom_name' => 'required_without:roster_member_id|string|max:255',
            'custom_email' => 'required_without:roster_member_id|email|max:255',
            'custom_phone' => 'required_without:roster_member_id|string|max:50',
            'priority' => 'sometimes|integer|min:1',
            'notes' => 'nullable|string',
            'send_invite' => 'sometimes|boolean',
        ]);

        // send_invite controls whether we fire a band-level invitation. It is
        // not a column on substitute_call_lists, so strip it before create().
        $sendInvite = $validated['send_invite'] ?? true;
        unset($validated['send_invite']);

        // Auto-assign priority if not provided.
        if (!isset($validated['priority'])) {
            $query = $band->substituteCallLists();

            if ($validated['band_role_id'] ?? null) {
                $query->where('band_role_id', $validated['band_role_id']);
            } elseif ($validated['instrument'] ?? null) {
                $query->where('instrument', $validated['instrument']);
            }

            $maxPriority = $query->max('priority') ?? 0;
            $validated['priority'] = $maxPriority + 1;
        }

        $validated['band_id'] = $band->id;

        $callListEntry = SubstituteCallList::create($validated);

        // Adding someone to a call list should also invite them to sub for the
        // band, unless the caller explicitly opts out.
        if ($sendInvite) {
            $this->maybeInviteToBand($band, $callListEntry, $subInvitationService);
        }

        return response()->json([
            'message' => 'Substitute added to call list',
            'entry' => $callListEntry->load('rosterMember.user'),
        ], 201);
    }

    /**
     * PATCH /api/mobile/bands/{band}/call-lists/{callList}
     *
     * Priority/notes only.
     */
    public function update(Request $request, Bands $band, SubstituteCallList $callList): JsonResponse
    {
        if ($callList->band_id !== $band->id) {
            abort(404);
        }

        $validated = $request->validate([
            'priority' => 'sometimes|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $callList->update($validated);

        return response()->json([
            'message' => 'Call list entry updated',
            'entry' => $callList->fresh()->load('rosterMember.user'),
        ]);
    }

    /**
     * DELETE /api/mobile/bands/{band}/call-lists/{callList}
     */
    public function destroy(Bands $band, SubstituteCallList $callList): JsonResponse
    {
        if ($callList->band_id !== $band->id) {
            abort(404);
        }

        $callList->delete();

        return response()->json([
            'message' => 'Substitute removed from call list',
        ]);
    }

    /**
     * POST /api/mobile/bands/{band}/call-lists/reorder
     */
    public function reorder(Request $request, Bands $band): JsonResponse
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

    /**
     * Send a band-level sub invitation for a newly added call-list entry.
     *
     * Invites a custom person (has custom_email) or a roster member whose
     * linked user is not already a band sub. Mirrors the web controller's
     * maybeInviteToBand().
     */
    protected function maybeInviteToBand(
        Bands $band,
        SubstituteCallList $entry,
        SubInvitationService $subInvitationService
    ): void {
        $email = null;
        $name = null;
        $phone = null;

        if ($entry->roster_member_id) {
            $rosterMember = RosterMember::with('user')->find($entry->roster_member_id);

            if (!$rosterMember) {
                return;
            }

            // If this roster member is a registered user already subbing for the
            // band, there's nothing to invite.
            if ($rosterMember->user_id) {
                $alreadySub = BandSubs::where('user_id', $rosterMember->user_id)
                    ->where('band_id', $band->id)
                    ->exists();

                if ($alreadySub) {
                    return;
                }
            }

            $email = $rosterMember->display_email;
            $name = $rosterMember->display_name;
            $phone = $rosterMember->phone;
        } else {
            $email = $entry->custom_email;
            $name = $entry->custom_name;
            $phone = $entry->custom_phone;
        }

        // We can't invite without an email address.
        if (empty($email)) {
            return;
        }

        $subInvitationService->inviteSubToBand(
            bandId: $band->id,
            email: $email,
            name: $name,
            phone: $phone,
            bandRoleId: $entry->band_role_id,
            notes: $entry->notes
        );
    }
}
