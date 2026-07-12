<?php

namespace App\Services\Chat;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Events;
use App\Models\Rehearsal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class ConversationService
{
    /**
     * Canonicalization rule (spec): an Events row wrapping a Rehearsal shares
     * the rehearsal's thread, so the event screen and rehearsal screen reach
     * ONE conversation. Bookings are NOT collapsed — a booking's thread is a
     * different discussion context than its performance events'.
     */
    public function canonicalTarget(Model $target): Model
    {
        if ($target instanceof Events && $target->eventable instanceof Rehearsal) {
            return $target->eventable;
        }

        return $target;
    }

    public function topicFor(Model $target): Conversation
    {
        $target = $this->canonicalTarget($target);

        $bandId = match (true) {
            $target instanceof Events => $target->eventable?->band_id,
            $target instanceof Rehearsal, $target instanceof Bookings => $target->band_id,
            default => null,
        };

        abort_if($bandId === null, 404, 'Band not found for this topic.');

        return $this->firstOrCreateByKey([
            'type'             => Conversation::TYPE_TOPIC,
            'band_id'          => (int) $bandId,
            'conversable_type' => get_class($target),
            'conversable_id'   => $target->getKey(),
            'unique_key'       => Conversation::topicKeyFor($target),
        ]);
    }

    public function bandChannelFor(Bands $band): Conversation
    {
        return $this->firstOrCreateByKey([
            'type'       => Conversation::TYPE_BAND,
            'band_id'    => $band->id,
            'unique_key' => Conversation::bandKeyFor($band->id),
        ]);
    }

    public function dmBetween(User $a, User $b): Conversation
    {
        $conversation = $this->firstOrCreateByKey([
            'type'       => Conversation::TYPE_DM,
            'unique_key' => Conversation::dmKeyFor($a->id, $b->id),
        ]);

        // DM participant rows are explicit (they ARE the access list).
        foreach ([$a->id, $b->id] as $userId) {
            ConversationParticipant::firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_id'         => $userId,
            ]);
        }

        return $conversation;
    }

    /**
     * Two users may DM when they share at least one band in any role
     * (owner/member/sub). Self-DM is not allowed.
     */
    public function canDm(User $a, User $b): bool
    {
        if ($a->id === $b->id) {
            return false;
        }

        $aBandIds = $a->allBands()->pluck('id');
        $bBandIds = $b->allBands()->pluck('id');

        return $aBandIds->intersect($bBandIds)->isNotEmpty();
    }

    /**
     * Lazily record that $user is in the thread and mark it read now.
     * Access itself is derived by ConversationPolicy — this row only powers
     * unread counts and read receipts.
     */
    public function touchParticipant(Conversation $conversation, User $user): ConversationParticipant
    {
        $participant = ConversationParticipant::firstOrCreate([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
        ]);

        $participant->forceFill(['last_read_at' => now()])->save();

        return $participant;
    }

    /** firstOrCreate with the unique_key race resolved by re-fetch. */
    private function firstOrCreateByKey(array $attributes): Conversation
    {
        $existing = Conversation::where('unique_key', $attributes['unique_key'])->first();
        if ($existing) {
            return $existing;
        }

        try {
            return Conversation::create($attributes);
        } catch (UniqueConstraintViolationException) {
            return Conversation::where('unique_key', $attributes['unique_key'])->firstOrFail();
        }
    }
}
