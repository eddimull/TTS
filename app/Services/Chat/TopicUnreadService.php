<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;

class TopicUnreadService
{
    /**
     * Batch unread counts for topic conversations.
     *
     * @param  array<int, array{0: class-string, 1: int}>  $pairs  conversable
     *         (morph type, id) pairs, e.g. [[Events::class, 12], [Rehearsal::class, 3]]
     * @return array<string, int> keyed "{type}:{id}"; zero-count and
     *         conversation-less pairs are omitted.
     */
    public function unreadCountsForConversables(User $user, array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $conversations = Conversation::query()
            ->where('type', Conversation::TYPE_TOPIC)
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as [$type, $id]) {
                    $q->orWhere(fn ($sub) => $sub
                        ->where('conversable_type', $type)
                        ->where('conversable_id', $id));
                }
            })
            ->get(['id', 'conversable_type', 'conversable_id']);

        if ($conversations->isEmpty()) {
            return [];
        }

        $ids = $conversations->pluck('id');

        // Split by read marker, mirroring ConversationsController::prefetchSummaryData.
        $withMarker = ConversationParticipant::query()
            ->whereIn('conversation_id', $ids)
            ->where('user_id', $user->id)
            ->whereNotNull('last_read_at')
            ->pluck('conversation_id');
        $withoutMarker = $ids->diff($withMarker)->values();

        $notMine = fn ($q) => $q
            ->where('messages.user_id', '!=', $user->id)
            ->orWhereNull('messages.user_id');

        $unread = collect();
        if ($withMarker->isNotEmpty()) {
            $unread = Message::query()
                ->whereIn('messages.conversation_id', $withMarker)
                ->where($notMine)
                ->join('conversation_participants', function ($join) use ($user) {
                    $join->on('conversation_participants.conversation_id', '=', 'messages.conversation_id')
                        ->where('conversation_participants.user_id', '=', $user->id);
                })
                ->whereColumn('messages.created_at', '>', 'conversation_participants.last_read_at')
                ->selectRaw('messages.conversation_id as conversation_id, COUNT(*) as unread')
                ->groupBy('messages.conversation_id')
                ->pluck('unread', 'conversation_id');
        }
        if ($withoutMarker->isNotEmpty()) {
            $unread = $unread->union(
                Message::query()
                    ->whereIn('messages.conversation_id', $withoutMarker)
                    ->where($notMine)
                    ->selectRaw('messages.conversation_id as conversation_id, COUNT(*) as unread')
                    ->groupBy('messages.conversation_id')
                    ->pluck('unread', 'conversation_id'),
            );
        }

        $out = [];
        foreach ($conversations as $c) {
            $count = (int) ($unread[$c->id] ?? 0);
            if ($count > 0) {
                $out["{$c->conversable_type}:{$c->conversable_id}"] = $count;
            }
        }

        return $out;
    }
}
