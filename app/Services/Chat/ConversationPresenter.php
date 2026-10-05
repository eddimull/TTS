<?php

namespace App\Services\Chat;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Events;
use App\Models\Message;
use App\Models\Rehearsal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * The one place that turns Conversation/Message rows into the wire shapes
 * both clients (mobile JSON API and web Inertia/axios) consume. Moved out of
 * Api\Mobile\ConversationsController so web and mobile cannot drift.
 */
final class ConversationPresenter
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly MessageFormatter $formatter,
        private readonly TopicUnreadService $topicUnread,
    ) {}

    /**
     * Every conversation the user can see, for the inbox / Messages list:
     * one band channel per owned/member band (lazily created so it is
     * always present), the user's DMs, and every topic thread they can view
     * that someone has actually posted in. One prefetch for all ids; rows
     * in the frozen summary shape, newest message first.
     *
     * @return Collection<int, array>
     */
    public function listFor(User $user): Collection
    {
        $channels = $user->bands()->unique('id')->values()
            ->map(fn ($band) => $this->conversations->bandChannelFor($band));

        $dms = Conversation::where('type', Conversation::TYPE_DM)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $all = $channels->concat($dms)->concat($this->visibleTopics($user));

        $prefetch = $this->prefetch($all->pluck('id'), $user);

        return $all->map(fn (Conversation $c) => $this->summarize($c, $user, $prefetch))
            ->sortByDesc(fn ($row) => $row['last_message_at'] ?? '')
            ->values();
    }

    /** Sum of unread_count across listFor() — the header badge number. */
    public function unreadTotalFor(User $user): int
    {
        return (int) $this->listFor($user)->sum('unread_count');
    }

    /**
     * Topic threads the user may see. Candidates are narrowed in SQL to the
     * bands the user has any standing in (owner/member/sub) and to threads
     * someone has posted in (soft-deleted messages still count — a thread
     * whose only message was deleted stays listed with a null preview).
     * Final visibility is ConversationPolicy; the conversable morph is
     * eager-loaded because the policy and topicTitle() read it per row.
     *
     * @return Collection<int, Conversation>
     */
    private function visibleTopics(User $user): Collection
    {
        $bandIds = $user->allBands()->pluck('id');

        if ($bandIds->isEmpty()) {
            return collect();
        }

        return Conversation::where('type', Conversation::TYPE_TOPIC)
            ->whereIn('band_id', $bandIds)
            ->whereHas('messages', fn ($q) => $q->withTrashed())
            ->with(['conversable' => fn (MorphTo $morph) => $morph->morphWith([
                Rehearsal::class => ['events', 'rehearsalSchedule'],
            ])])
            ->get()
            ->filter(fn (Conversation $c) => $user->can('view', $c))
            ->values();
    }

    /**
     * Bulk-load everything summarize() needs for a set of conversations so
     * list endpoints run a constant number of queries instead of ~3 per row.
     *
     * @param  Collection<int, int>  $ids
     * @param  Collection|null  $lastReads  conversation_id => last_read_at for $user.
     *         Pass null to look them up; pass collect() to force the
     *         "no marker" bucket (storeDm relies on this).
     * @return array{last: Collection, unread: Collection, dmOther: Collection}
     */
    public function prefetch(Collection $ids, User $user, ?Collection $lastReads = null): array
    {
        $lastReads ??= ConversationParticipant::where('user_id', $user->id)
            ->whereIn('conversation_id', $ids)
            ->pluck('last_read_at', 'conversation_id');

        $latestIds = Message::withTrashed()
            ->whereIn('conversation_id', $ids)
            ->selectRaw('MAX(id) as id')
            ->groupBy('conversation_id')
            ->pluck('id');

        $last = Message::withTrashed()
            ->whereIn('id', $latestIds)
            ->with('attachments')
            ->get()
            ->keyBy('conversation_id');

        // Grouped count of "not mine" messages per conversation newer than that
        // conversation's own last_read_at. Two aggregate queries (with marker /
        // without marker) — O(1) queries, never O(messages) rows into PHP. A
        // participant row with NULL last_read_at falls into the no-marker bucket.
        $withMarkerIds    = $lastReads->filter(fn ($v) => $v !== null)->keys();
        $withoutMarkerIds = collect($ids)->diff($withMarkerIds);

        // pluck() keys by integer conversation_id; Collection::merge() would
        // re-index integer keys, so union() is required to keep the keying.
        $unread = collect();

        if ($withMarkerIds->isNotEmpty()) {
            $unread = $unread->union(
                Message::query()
                    ->whereIn('messages.conversation_id', $withMarkerIds)
                    ->where(fn ($q) => $q->where('messages.user_id', '!=', $user->id)
                        ->orWhereNull('messages.user_id'))
                    ->join('conversation_participants', function ($join) use ($user) {
                        $join->on('conversation_participants.conversation_id', '=', 'messages.conversation_id')
                            ->where('conversation_participants.user_id', '=', $user->id);
                    })
                    ->whereColumn('messages.created_at', '>', 'conversation_participants.last_read_at')
                    ->selectRaw('messages.conversation_id as conversation_id, COUNT(*) as unread')
                    ->groupBy('messages.conversation_id')
                    ->pluck('unread', 'conversation_id')
                    ->map(fn ($n) => (int) $n)
            );
        }

        if ($withoutMarkerIds->isNotEmpty()) {
            $unread = $unread->union(
                Message::query()
                    ->whereIn('conversation_id', $withoutMarkerIds->values())
                    ->where(fn ($q) => $q->where('user_id', '!=', $user->id)
                        ->orWhereNull('user_id'))
                    ->selectRaw('conversation_id, COUNT(*) as unread')
                    ->groupBy('conversation_id')
                    ->pluck('unread', 'conversation_id')
                    ->map(fn ($n) => (int) $n)
            );
        }

        $dmOther = ConversationParticipant::whereIn('conversation_id', $ids)
            ->where('user_id', '!=', $user->id)
            ->with('user')
            ->get()
            ->keyBy('conversation_id');

        return ['last' => $last, 'unread' => $unread, 'dmOther' => $dmOther];
    }

    /**
     * Conversation JSON — the one wire shape for a conversation everywhere.
     *
     * @param array{last: Collection, unread: Collection, dmOther: Collection} $prefetch
     */
    public function summarize(Conversation $conversation, User $user, array $prefetch): array
    {
        $last = $prefetch['last']->get($conversation->id);

        $preview = null;
        $lastAt  = null;
        if ($last) {
            $lastAt  = $last->created_at->toIso8601String();
            $preview = $last->trashed()
                ? null
                : (($last->body !== null && $last->body !== '') ? $last->body : '📷 Photo');
        }

        $unread = $prefetch['unread']->get($conversation->id, 0);

        $title = match ($conversation->type) {
            Conversation::TYPE_BAND  => $conversation->band?->name ?? 'Band',
            Conversation::TYPE_DM    => $prefetch['dmOther']->get($conversation->id)?->user?->name ?? 'Direct message',
            Conversation::TYPE_TOPIC => $this->topicTitle($conversation),
            default => 'Conversation',
        };

        return [
            'id'                   => $conversation->id,
            'type'                 => $conversation->type,
            'band_id'              => $conversation->band_id ? (int) $conversation->band_id : null,
            'title'                => $title,
            'topic_type'           => $this->topicType($conversation),
            'last_message_preview' => $preview,
            'last_message_at'      => $lastAt,
            'unread_count'         => $unread,
            'can_moderate'         => $user->can('moderate', $conversation),
        ];
    }

    /**
     * Row icon discriminator. Frozen wire contract: 'booking' | 'event' |
     * 'rehearsal' for topics, null otherwise. Derived from the loaded
     * conversable so a thread whose item was deleted reports null.
     */
    public function topicType(Conversation $conversation): ?string
    {
        if ($conversation->type !== Conversation::TYPE_TOPIC) {
            return null;
        }

        return match (true) {
            $conversation->conversable instanceof Bookings  => 'booking',
            $conversation->conversable instanceof Events    => 'event',
            $conversation->conversable instanceof Rehearsal => 'rehearsal',
            default => null,
        };
    }

    /**
     * The item's human name. Bookings carry `name`, Events carry `title`,
     * Rehearsals fall back to the child event's title, then the schedule
     * name (same chain as Rehearsal::getGoogleCalendarSummary()).
     */
    public function topicTitle(Conversation $conversation): string
    {
        $target = $conversation->conversable;

        $title = match (true) {
            $target instanceof Bookings  => $target->name,
            $target instanceof Events    => $target->title,
            // ->events (not ->events()) so an eager-loaded collection is reused.
            $target instanceof Rehearsal => $target->events->first()?->title
                ?? $target->rehearsalSchedule?->name,
            default => null,
        };

        $title = is_string($title) ? trim($title) : '';

        if ($title !== '') {
            return $title;
        }

        return $target instanceof Rehearsal ? 'Rehearsal' : 'Thread';
    }

    /**
     * The shared ThreadPage shape. Messages come back oldest→newest;
     * `channel` is what the client subscribes to for live updates.
     *
     * @return array{conversation: array, messages: Collection, participants: Collection, channel: string, has_more: bool}
     */
    public function threadPage(User $user, Conversation $conversation, ?int $before = null): array
    {
        $limit = 50;

        $page = $conversation->messages()->withTrashed()
            ->with(['user', 'attachments', 'reactions'])
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->latest('id')->limit($limit + 1)->get();

        $hasMore  = $page->count() > $limit;
        $messages = $page->take($limit)->reverse()->values()
            ->map(fn ($m) => $this->formatter->format($m));

        $participants = $conversation->participants()->with('user')->get()
            ->map(fn ($p) => [
                'user_id'           => (int) $p->user_id,
                'name'              => $p->user?->name,
                'avatar_url'        => null,
                'last_read_at'      => $p->last_read_at?->toIso8601String(),
                'last_delivered_at' => $p->last_delivered_at?->toIso8601String(),
            ])->values();

        $prefetch = $this->prefetch(collect([$conversation->id]), $user);

        return [
            'conversation' => $this->summarize($conversation, $user, $prefetch),
            'messages'     => $messages,
            'participants' => $participants,
            'channel'      => 'private-conversation.' . $conversation->id,
            'has_more'     => $hasMore,
        ];
    }

    /**
     * Unread comment count for a page's item WITHOUT creating the thread.
     * Returns null when the user could not view that topic at all (so the
     * page can hide its Comments button), 0 when there is no thread yet.
     */
    public function unreadCountFor(User $user, Model $target): ?int
    {
        $target = $this->conversations->canonicalTarget($target);

        $bandId = match (true) {
            $target instanceof Events => $target->eventable?->band_id,
            $target instanceof Rehearsal, $target instanceof Bookings => $target->band_id,
            default => null,
        };

        if ($bandId === null) {
            return null;
        }

        // ConversationPolicy::viewTopic reads only band_id + the conversable,
        // so an unsaved probe answers "could this user see the thread?".
        $probe = (new Conversation())->forceFill([
            'type'    => Conversation::TYPE_TOPIC,
            'band_id' => (int) $bandId,
        ]);
        $probe->setRelation('conversable', $target);

        if ($user->cannot('view', $probe)) {
            return null;
        }

        $key    = get_class($target) . ':' . $target->getKey();
        $counts = $this->topicUnread->unreadCountsForConversables($user, [[get_class($target), (int) $target->getKey()]]);

        return (int) ($counts[$key] ?? 0);
    }
}
