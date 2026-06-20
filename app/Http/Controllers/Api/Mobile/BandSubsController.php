<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\BandSubInvitation;
use App\Models\BandSubs;
use App\Services\SubInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile band subs management under /api/mobile/bands/{band}/subs.
 *
 * Band ownership is enforced by the `owner` middleware on the route group, so
 * no in-controller authorize() call is needed — we only confirm that the bound
 * {invitation}/{user} link actually belongs to the {band} (404 if not). The
 * belongs-to-band guard is inline so it runs before any other check.
 */
class BandSubsController extends Controller
{
    /**
     * GET /api/mobile/bands/{band}/subs
     *
     * Unified list of the band's subs: confirmed band_subs (status=active) and
     * pending band_sub_invitations (status=pending).
     */
    public function index(Bands $band): JsonResponse
    {
        $active = BandSubs::where('band_id', $band->id)
            ->with('user')
            ->get()
            ->filter(fn ($sub) => $sub->user !== null)
            ->map(fn ($sub) => [
                'id' => $sub->id,
                'type' => 'band_sub',
                'status' => 'active',
                'is_registered' => true,
                'user_id' => $sub->user_id,
                'name' => $sub->user->name,
                'email' => $sub->user->email,
                'phone' => null,
                'band_role_id' => null,
                'role_name' => null,
            ])
            ->values();

        $pending = BandSubInvitation::where('band_id', $band->id)
            ->pending()
            ->with(['user', 'bandRole'])
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'type' => 'invitation',
                'status' => 'pending',
                'is_registered' => $inv->isRegisteredUser(),
                'user_id' => $inv->user_id,
                'name' => $inv->display_name,
                'email' => $inv->display_email,
                'phone' => $inv->display_phone,
                'band_role_id' => $inv->band_role_id,
                'role_name' => $inv->role_name,
            ])
            ->values();

        return response()->json([
            'subs' => $active->concat($pending)->values(),
        ]);
    }

    /**
     * POST /api/mobile/bands/{band}/subs/invite
     */
    public function invite(Request $request, Bands $band, SubInvitationService $subInvitationService): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'band_role_id' => 'nullable|exists:band_roles,id',
            'notes' => 'nullable|string',
        ]);

        $invitation = $subInvitationService->inviteSubToBand(
            bandId: $band->id,
            email: $validated['email'],
            name: $validated['name'] ?? null,
            phone: $validated['phone'] ?? null,
            bandRoleId: $validated['band_role_id'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Sub invitation sent',
            'invitation' => [
                'id' => $invitation->id,
                'status' => $invitation->pending ? 'pending' : 'active',
                'is_registered' => $invitation->isRegisteredUser(),
                'user_id' => $invitation->user_id,
                'name' => $invitation->display_name,
                'email' => $invitation->display_email,
                'band_role_id' => $invitation->band_role_id,
                'role_name' => $invitation->role_name,
            ],
        ], 201);
    }

    /**
     * DELETE /api/mobile/bands/{band}/subs/invitations/{invitation}
     *
     * Revoke a pending band invitation.
     */
    public function destroyInvitation(Bands $band, BandSubInvitation $invitation): JsonResponse
    {
        if ($invitation->band_id !== $band->id) {
            abort(404);
        }

        $invitation->delete();

        return response()->json(null, 204);
    }

    /**
     * DELETE /api/mobile/bands/{band}/subs/{user}
     *
     * Remove an active band sub link for the given user.
     */
    public function destroy(Bands $band, int $user): JsonResponse
    {
        $bandSub = BandSubs::where('band_id', $band->id)
            ->where('user_id', $user)
            ->first();

        if (!$bandSub) {
            abort(404);
        }

        $bandSub->delete();

        return response()->json(null, 204);
    }
}
