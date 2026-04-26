<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Invitations;
use App\Models\User;
use App\Services\BandMemberRemovalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BandSettingsController extends Controller
{
    public function __construct(
        private BandMemberRemovalService $removalService
    ) {}

    public function show(Bands $band): JsonResponse
    {
        return response()->json([
            'band' => [
                'id'       => $band->id,
                'name'     => $band->name,
                'site_name'=> $band->site_name,
                'address'  => $band->address ?? '',
                'city'     => $band->city ?? '',
                'state'    => $band->state ?? '',
                'zip'      => $band->zip ?? '',
                'logo_url' => $band->logo ? asset('storage/' . ltrim($band->logo, '/')) : null,
            ],
        ]);
    }

    public function update(Request $request, Bands $band): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'site_name' => 'required|string|max:255|unique:bands,site_name,' . $band->id,
            'address'   => 'nullable|string|max:255',
            'city'      => 'nullable|string|max:100',
            'state'     => 'nullable|string|max:100',
            'zip'       => 'nullable|string|max:20',
        ]);
        $band->update($data);
        return response()->json(['band' => $band->fresh()]);
    }

    public function uploadLogo(Request $request, Bands $band): JsonResponse
    {
        $request->validate(['logo' => 'required|image|max:5120']);
        $path = $request->file('logo')->store("bands/{$band->id}/logo", 'public');
        $band->update(['logo' => $path]);
        return response()->json(['logo_url' => asset('storage/' . $path)]);
    }

    public function members(Bands $band): JsonResponse
    {
        setPermissionsTeamId($band->id);

        $ownerIds  = $band->owners()->pluck('user_id')->toArray();
        $memberIds = $band->members()->pluck('user_id')->toArray();
        $allUserIds = array_unique(array_merge($ownerIds, $memberIds));

        $members = User::whereIn('id', $allUserIds)->get()->map(function (User $user) use ($ownerIds) {
            $isOwner = in_array($user->id, $ownerIds);
            $perms = [];
            foreach ($this->allPermissionNames() as $perm) {
                $perms[$perm] = $isOwner || $user->hasPermissionTo($perm);
            }
            return [
                'id'          => $user->id,
                'name'        => $user->name,
                'is_owner'    => $isOwner,
                'permissions' => $perms,
            ];
        });

        return response()->json(['members' => $members]);
    }

    public function removeMember(Bands $band, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $this->removalService->removeFromBand($band, $user);
        return response()->json(null, 204);
    }

    public function setPermission(Request $request, Bands $band, int $userId): JsonResponse
    {
        $data = $request->validate([
            'permission' => 'required|string',
            'granted'    => 'required|boolean',
        ]);

        $user = User::findOrFail($userId);
        setPermissionsTeamId($band->id);

        if ($data['granted']) {
            $user->givePermissionTo($data['permission']);
        } else {
            $user->revokePermissionTo($data['permission']);
        }

        return response()->json(['ok' => true]);
    }

    public function invitations(Bands $band): JsonResponse
    {
        $invitations = $band->invitations()
            ->where('pending', true)
            ->get()
            ->map(fn($inv) => [
                'id'          => $inv->id,
                'email'       => $inv->email,
                'invite_type' => $inv->invite_type_id === 1 ? 'owner' : 'member',
                'key'         => $inv->key,
            ]);

        return response()->json(['invitations' => $invitations]);
    }

    public function revokeInvitation(Bands $band, Invitations $invitation): JsonResponse
    {
        abort_if($invitation->band_id !== $band->id, 403);
        $invitation->update(['pending' => false]);
        return response()->json(null, 204);
    }

    private function allPermissionNames(): array
    {
        return [
            'read:events', 'write:events',
            'read:bookings', 'write:bookings',
            'read:rehearsals', 'write:rehearsals',
            'read:charts', 'write:charts',
            'read:songs', 'write:songs',
            'read:media', 'write:media',
            'read:invoices', 'write:invoices',
            'read:proposals', 'write:proposals',
            'read:colors', 'write:colors',
        ];
    }
}
