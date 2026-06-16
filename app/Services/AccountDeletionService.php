<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\EventSubs;
use App\Models\Invitations;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    public function __construct(protected BandMemberRemovalService $removalService) {}

    /**
     * Permanently delete a user account and all of its personal associations.
     *
     * Shared band data is preserved: the user is cleanly detached from every
     * band via BandMemberRemovalService (which keeps the band and its other
     * members intact). A band the user solely owned is left ownerless rather
     * than destroyed — its remaining members keep their data.
     */
    public function deleteAccount(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Detach from every band the user belongs to (owner/member/sub).
            // allBands() de-dupes, so each band is processed once.
            foreach ($user->allBands() as $band) {
                $this->removalService->removeFromBand($band, $user);
            }

            // Personal records keyed directly to the user.
            DeviceToken::where('user_id', $user->id)->delete();

            // Pending invitations / sub-invitations addressed to this email that
            // were never accepted. Accepted ones are already cleaned up per-band
            // by removeFromBand(); these are the unconsumed, email-keyed leftovers.
            Invitations::where('email', $user->email)->where('pending', true)->delete();
            EventSubs::where('email', $user->email)->where('pending', true)->delete();

            // Sanctum personal access tokens (all devices).
            $user->tokens()->delete();

            $user->delete();
        });
    }
}
