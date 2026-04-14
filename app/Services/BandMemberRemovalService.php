<?php

namespace App\Services;

use App\Models\BandSubs;
use App\Models\Bands;
use App\Models\CalendarAccess;
use App\Models\EventMember;
use App\Models\EventSubs;
use App\Models\RosterMember;
use App\Models\User;
use App\Models\userPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BandMemberRemovalService
{
    public function __construct(protected GoogleCalendarService $googleCalendarService) {}

    /**
     * Completely remove a user from a band, cleaning up all associated data.
     */
    public function removeFromBand(Bands $band, User $user): void
    {
        DB::transaction(function () use ($band, $user) {
            $this->revokeCalendarAccess($band, $user);
            $this->revokePermissionsAndRoles($band, $user);
            $this->removeLegacyPermissions($band, $user);
            $this->removeFromPaymentGroups($band, $user);
            $this->removeFromFutureEvents($band, $user);
            $this->removeFromRosters($band, $user);
            $this->removeBandSubRegistration($band, $user);
            $this->removePendingEventSubInvitations($band, $user);
            $this->orphanMediaFiles($band, $user);
            $band->members()->where('user_id', $user->id)->delete();
            $band->owners()->where('user_id', $user->id)->delete();
        });
    }

    /**
     * Revoke Google Calendar ACL access and delete local calendar_access records.
     */
    private function revokeCalendarAccess(Bands $band, User $user): void
    {
        $band->load('calendars');

        foreach ($band->calendars as $calendar) {
            $access = CalendarAccess::where('band_calendar_id', $calendar->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$access) {
                continue;
            }

            try {
                $googleCalendar = $calendar->googleCalendar;
                $acl = $this->googleCalendarService->findAccess($googleCalendar, $user->email);
                $this->googleCalendarService->revokeAccess($googleCalendar, $acl);
            } catch (\Exception $e) {
                Log::warning("Could not revoke Google Calendar ACL for user {$user->id} on calendar {$calendar->id}: {$e->getMessage()}");
            }

            $access->delete();
        }
    }

    /**
     * Revoke all Spatie roles and permissions scoped to this band.
     */
    private function revokePermissionsAndRoles(Bands $band, User $user): void
    {
        setPermissionsTeamId($band->id);
        $user->roles()->detach();
        $user->permissions()->detach();
        setPermissionsTeamId(0);
    }

    /**
     * Remove the user from all payment groups for this band.
     */
    private function removeFromPaymentGroups(Bands $band, User $user): void
    {
        $band->paymentGroups()->each(function ($group) use ($user) {
            $group->users()->detach($user->id);
        });
    }

    /**
     * Remove the user from future event members for this band.
     */
    private function removeFromFutureEvents(Bands $band, User $user): void
    {
        EventMember::where('band_id', $band->id)
            ->where('user_id', $user->id)
            ->whereHas('event', fn($q) => $q->where('date', '>=', now()))
            ->delete();
    }

    /**
     * Detach the user from all roster members for this band.
     * Keeps the roster slot but nullifies the user link so non-user info can be filled in.
     */
    private function removeFromRosters(Bands $band, User $user): void
    {
        RosterMember::whereHas('roster', fn($q) => $q->where('band_id', $band->id))
            ->where('user_id', $user->id)
            ->update(['user_id' => null, 'is_active' => false]);
    }

    /**
     * Remove the legacy user_permissions record for this band.
     * This table still receives writes via User::canWriteCharts() / permissionsForBand().
     */
    private function removeLegacyPermissions(Bands $band, User $user): void
    {
        userPermissions::where('band_id', $band->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Remove the user from the band's substitute registry.
     * This also cascades cleanly since EventSubs queries via user_id + band_id.
     */
    private function removeBandSubRegistration(Bands $band, User $user): void
    {
        BandSubs::where('band_id', $band->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Remove pending event sub invitations for this user in this band.
     * Accepted invitations on past events are kept for historical records.
     */
    private function removePendingEventSubInvitations(Bands $band, User $user): void
    {
        EventSubs::where('band_id', $band->id)
            ->where('user_id', $user->id)
            ->where('pending', true)
            ->delete();
    }

    /**
     * Orphan media files uploaded by the user for this band.
     * Files are kept (they belong to the band, not the user) but the uploader link is nullified.
     */
    private function orphanMediaFiles(Bands $band, User $user): void
    {
        \App\Models\MediaFile::where('band_id', $band->id)
            ->where('user_id', $user->id)
            ->update(['user_id' => null]);
    }
}
