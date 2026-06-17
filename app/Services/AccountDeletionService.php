<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\EventSubs;
use App\Models\Invitations;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class AccountDeletionService
{
    public function __construct(protected BandMemberRemovalService $removalService) {}

    /**
     * Build the signed, expiring URL for the account-deletion confirmation page.
     * Shared by the web and mobile request flows so both email the same neutral
     * confirmation link.
     *
     * Points at the GET confirmation PAGE (not the deleting action). The page
     * POSTs back to the same signed URL to actually delete — so a link
     * prefetch/scanner (which only issues GETs) can never trigger the
     * irreversible delete.
     */
    public static function confirmationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'account.confirm-deletion',
            now()->addMinutes(60),
            ['user' => $user->id],
        );
    }

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
