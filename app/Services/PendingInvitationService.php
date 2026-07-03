<?php

namespace App\Services;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\BandSubInvitation;
use App\Models\EventSubs;
use App\Models\Invitations;
use App\Models\User;

class PendingInvitationService
{
    public const OWNER_INVITE_TYPE = 1;
    public const MEMBER_INVITE_TYPE = 2;

    /**
     * Consume any pending sub-invitations and band invitations addressed to
     * this user's email, assigning the corresponding roles. Shared by email
     * registration and social sign-up so the two paths cannot drift.
     */
    public function applyFor(User $user): void
    {
        $subInvitations = EventSubs::where('email', $user->email)
            ->where('pending', true)
            ->get();

        if ($subInvitations->isNotEmpty()) {
            $service = new SubInvitationService();
            foreach ($subInvitations as $eventSub) {
                $service->acceptInvitation($eventSub->invitation_key, $user);
            }
        }

        $bandSubInvitations = BandSubInvitation::where('email', $user->email)
            ->where('pending', true)
            ->get();

        if ($bandSubInvitations->isNotEmpty()) {
            $service = $service ?? new SubInvitationService();
            foreach ($bandSubInvitations as $bandInvitation) {
                $service->acceptBandInvitation($bandInvitation->invitation_key, $user);
            }
        }

        $invitations = Invitations::where('email', $user->email)
            ->where('pending', true)
            ->get();

        foreach ($invitations as $invitation) {
            if ($invitation->invite_type_id === self::OWNER_INVITE_TYPE) {
                BandOwners::create([
                    'user_id' => $user->id,
                    'band_id' => $invitation->band_id,
                ]);
                setPermissionsTeamId($invitation->band_id);
                $user->assignRole('band-owner');
                setPermissionsTeamId(null);
            }
            if ($invitation->invite_type_id === self::MEMBER_INVITE_TYPE) {
                BandMembers::create([
                    'user_id' => $user->id,
                    'band_id' => $invitation->band_id,
                ]);
                $user->assignBandMemberDefaults($invitation->band_id);
            }
            $invitation->pending = false;
            $invitation->save();
        }
    }
}
