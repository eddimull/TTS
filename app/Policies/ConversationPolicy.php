<?php

namespace App\Policies;

use App\Models\Bookings;
use App\Models\Conversation;
use App\Models\Events;
use App\Models\Rehearsal;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return match ($conversation->type) {
            Conversation::TYPE_DM => $conversation->participants()
                ->where('user_id', $user->id)->exists(),

            // Owners + members only. Deliberately NOT canRead('events'):
            // that returns true for subs, who are excluded from the channel.
            Conversation::TYPE_BAND => $user->ownsBand($conversation->band_id)
                || $user->isPartOfBand($conversation->band_id),

            Conversation::TYPE_TOPIC => $this->viewTopic($user, $conversation),

            default => false,
        };
    }

    /** Everyone who can see a thread can post in it. */
    public function post(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    /** Delete others' messages: band/topic only, owner or moderate:chat. */
    public function moderate(User $user, Conversation $conversation): bool
    {
        if ($conversation->type === Conversation::TYPE_DM) {
            return false;
        }

        return $user->canModerateChat($conversation->band_id);
    }

    private function viewTopic(User $user, Conversation $conversation): bool
    {
        $bandId = (int) $conversation->band_id;
        $target = $conversation->conversable;

        if (!$target) {
            return false;
        }

        $isOwnerOrMember = $user->ownsBand($bandId) || $user->isPartOfBand($bandId);

        if ($target instanceof Bookings) {
            // canRead('bookings') has no sub shortcut, so subs are excluded here.
            return $isOwnerOrMember && $user->canRead('bookings', $bandId);
        }

        if ($target instanceof Rehearsal) {
            if ($isOwnerOrMember) {
                return $user->canRead('rehearsals', $bandId);
            }

            // Sub path: entitled to ANY Events row wrapping this rehearsal.
            return $target->events()->pluck('id')
                ->contains(fn ($eventId) => $user->isEntitledToEvent((int) $eventId));
        }

        if ($target instanceof Events) {
            if ($isOwnerOrMember) {
                return $user->canRead('events', $bandId);
            }

            return $user->isEntitledToEvent($target->id);
        }

        return false;
    }
}
