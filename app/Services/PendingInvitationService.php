<?php

namespace App\Services;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\BandSubInvitation;
use App\Models\BandSubs;
use App\Models\EventMember;
use App\Models\EventSubs;
use App\Models\Invitations;
use App\Models\RehearsalSub;
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

        $this->linkOrphanedAssignments($user);
    }

    /**
     * Event slot assignments (event_members) and rehearsal invites
     * (rehearsal_subs) created before this account existed reference the
     * person only by email, with user_id NULL. Calendar and rehearsal
     * visibility key on user_id, so link those rows to the new account now.
     *
     * Rows are skipped when the user already holds a row for the same
     * event/rehearsal — the unique indexes on (event_id, user_id) and
     * (rehearsal_id, user_id) still contain soft-deleted rows.
     */
    protected function linkOrphanedAssignments(User $user): void
    {
        $linked = false;

        $orphanedMembers = EventMember::whereNull('user_id')
            ->where('email', $user->email)
            ->get();

        foreach ($orphanedMembers as $member) {
            $conflict = EventMember::withTrashed()
                ->where('event_id', $member->event_id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$conflict) {
                // Saving through the model lets the EventMember hook create
                // the band_subs row the mobile band-access middleware needs.
                $member->update(['user_id' => $user->id]);
                $linked = true;
            }
        }

        $orphanedRehearsalSubs = RehearsalSub::whereNull('user_id')
            ->where('email', $user->email)
            ->get();

        foreach ($orphanedRehearsalSubs as $rehearsalSub) {
            $conflict = RehearsalSub::withTrashed()
                ->where('rehearsal_id', $rehearsalSub->rehearsal_id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$conflict) {
                $rehearsalSub->update(['user_id' => $user->id]);
                BandSubs::firstOrCreate([
                    'user_id' => $user->id,
                    'band_id' => $rehearsalSub->band_id,
                ]);
                $linked = true;
            }
        }

        if ($linked) {
            // The sub-only calendar path requires the global `sub` role
            // (UserEventsService pins team 0 before hasRole('sub')).
            $previousTeam = getPermissionsTeamId();
            setPermissionsTeamId(0);
            $user->unsetRelation('roles');
            if (!$user->hasRole('sub')) {
                $user->assignRole('sub');
            }
            setPermissionsTeamId($previousTeam);
        }
    }
}
