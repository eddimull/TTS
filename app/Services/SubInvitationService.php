<?php

namespace App\Services;

use App\Models\EventSubs;
use App\Models\Events;
use App\Models\User;
use App\Models\BandSubs;
use App\Models\Bands;
use App\Mail\SubInvitation;
use App\Notifications\TTSNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubInvitationService
{
    /**
     * Invite a substitute to an event
     *
     * @param int $eventId
     * @param int $bandId
     * @param string $email
     * @param string|null $name
     * @param string|null $phone
     * @param int|null $bandRoleId (instrument/role)
     * @param int|null $payoutAmount (in cents)
     * @param string|null $notes
     * @return EventSubs
     */
    public function inviteSubToEvent(
        int $eventId,
        int $bandId,
        string $email,
        ?string $name = null,
        ?string $phone = null,
        ?int $bandRoleId = null,
        ?int $payoutAmount = null,
        ?string $notes = null
    ): EventSubs {
        $event = Events::findOrFail($eventId);
        $band = Bands::findOrFail($bandId);

        // Check if user exists
        $user = User::where('email', $email)->first();

        // Create event_subs record
        $eventSub = EventSubs::create([
            'event_id' => $eventId,
            'band_id' => $bandId,
            'band_role_id' => $bandRoleId,
            'user_id' => $user?->id,
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'payout_amount' => $payoutAmount,
            'notes' => $notes,
            'pending' => true,
        ]);

        // If user exists, add them to band_subs if not already there
        if ($user) {
            BandSubs::firstOrCreate([
                'user_id' => $user->id,
                'band_id' => $bandId,
            ]);

            // Assign sub role if they don't have it
            if (!$user->hasRole('sub')) {
                $user->assignRole('sub');
            }
        }

        // Send invitation email
        $this->sendInvitationEmail($eventSub, $event, $band);

        // Notify band owners
        $this->notifyBandOwners($band, $eventSub, $event);

        return $eventSub;
    }

    /**
     * Send invitation email to the substitute
     */
    protected function sendInvitationEmail(EventSubs $eventSub, Events $event, Bands $band): void
    {
        $invitationUrl = route('sub.invitation.show', ['key' => $eventSub->invitation_key]);

        Mail::to($eventSub->display_email)->send(
            new SubInvitation($eventSub, $event, $band, $invitationUrl)
        );
    }

    /**
     * Notify band owners about the new sub invitation
     */
    protected function notifyBandOwners(Bands $band, EventSubs $eventSub, Events $event): void
    {
        foreach ($band->owners as $owner) {
            $ownerUser = $owner->user ?? $owner;

            $eventTitle = $event->title ?? 'an event';
            $message = $eventSub->isRegisteredUser()
                ? "{$eventSub->display_name} has been invited as a sub for {$eventTitle}"
                : "{$eventSub->email} (not yet registered) has been invited as a sub for {$eventTitle}";

            $ownerUser->notify(new TTSNotification([
                'emailHeader' => 'Sub Invited',
                'text' => $message,
                'route' => 'events.show',
                'routeParams' => $event->key,
                'actionText' => 'View Event',
                'url' => '/events/' . $event->key,
            ]));
        }
    }

    /**
     * Accept a sub invitation
     */
    public function acceptInvitation(string $invitationKey, User $user): EventSubs
    {
        $eventSub = EventSubs::where('invitation_key', $invitationKey)
            ->where('pending', true)
            ->firstOrFail();

        // Link user to the invitation if not already linked
        if (!$eventSub->user_id) {
            $eventSub->update(['user_id' => $user->id]);
        }

        // Ensure user is in band_subs
        BandSubs::firstOrCreate([
            'user_id' => $user->id,
            'band_id' => $eventSub->band_id,
        ]);

        // Assign sub role if they don't have it
        if (!$user->hasRole('sub')) {
            $user->assignRole('sub');
        }

        // Mark as accepted
        $eventSub->markAsAccepted();

        return $eventSub;
    }

    /**
     * Remove a sub from an event
     */
    public function removeSubFromEvent(int $eventSubId): bool
    {
        $eventSub = EventSubs::findOrFail($eventSubId);
        return $eventSub->delete();
    }

    /**
     * Get all pending invitations for a user
     */
    public function getPendingInvitationsForUser(User $user)
    {
        return EventSubs::forUser($user->id)
            ->pending()
            ->with(['event', 'band'])
            ->get();
    }

    /**
     * Get all events a user is subbing for
     */
    public function getEventsForSub(User $user)
    {
        return EventSubs::forUser($user->id)
            ->with(['event.eventable', 'band'])
            ->get()
            ->map(function ($eventSub) {
                return $eventSub->event;
            });
    }
}
