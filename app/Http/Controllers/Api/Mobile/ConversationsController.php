<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Events;
use App\Models\Message;
use App\Models\Rehearsal;
use App\Models\User;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConversationsController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly MessageFormatter $formatter,
    ) {}

    /**
     * GET /api/mobile/conversations — the Messages screen: the user's DMs
     * plus a band channel per owned/member band (lazily created so it is
     * always present). Topic threads are NOT listed; they surface on their
     * event/rehearsal/booking screens.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $channels = $user->bands()->unique('id')->values()
            ->map(fn ($band) => $this->conversations->bandChannelFor($band));

        $dms = Conversation::where('type', Conversation::TYPE_DM)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $all = $channels->concat($dms);
        $ids = $all->pluck('id');

        $lastReads = ConversationParticipant::where('user_id', $user->id)
            ->whereIn('conversation_id', $ids)
            ->pluck('last_read_at', 'conversation_id');

        $prefetch = $this->prefetchSummaryData($ids, $user, $lastReads);

        $rows = $all->map(fn (Conversation $c) => $this->summarize($c, $user, $prefetch))
            ->sortByDesc(fn ($row) => $row['last_message_at'] ?? '')
            ->values();

        return response()->json(['conversations' => $rows]);
    }

    /**
     * Bulk-load everything summarize() needs for a set of conversations so
     * index() runs a constant number of queries instead of ~3 per row.
     *
     * @return array{last: \Illuminate\Support\Collection, unread: \Illuminate\Support\Collection, dmOther: \Illuminate\Support\Collection}
     */
    private function prefetchSummaryData($ids, User $user, $lastReads): array
    {
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

        // Grouped count of "not mine" messages per conversation that are
        // newer than that conversation's own last_read_at, in one query via
        // a per-row conditional (each conversation's threshold differs).
        $unread = Message::whereIn('conversation_id', $ids)
            ->where('user_id', '!=', $user->id)
            ->get(['conversation_id', 'created_at'])
            ->groupBy('conversation_id')
            ->map(function ($messages, $conversationId) use ($lastReads) {
                $lastReadAt = $lastReads->get($conversationId);

                return $lastReadAt
                    ? $messages->filter(fn ($m) => $m->created_at->gt($lastReadAt))->count()
                    : $messages->count();
            });

        $dmOther = ConversationParticipant::whereIn('conversation_id', $ids)
            ->where('user_id', '!=', $user->id)
            ->with('user')
            ->get()
            ->keyBy('conversation_id');

        return ['last' => $last, 'unread' => $unread, 'dmOther' => $dmOther];
    }

    /** POST /api/mobile/conversations/dm {user_id} — find-or-create the global pair thread. */
    public function storeDm(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $me    = $request->user();
        $other = User::findOrFail($validated['user_id']);

        abort_unless($this->conversations->canDm($me, $other), 403, 'You do not share a band with this user.');

        $conversation = $this->conversations->dmBetween($me, $other);

        $prefetch = $this->prefetchSummaryData(collect([$conversation->id]), $me, collect());

        return response()->json(['conversation' => $this->summarize($conversation, $me, $prefetch)]);
    }

    /** GET /api/mobile/chat/contacts — who the current user may start a DM with. */
    public function contacts(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var array<int, array{bands: list<string>, is_sub: bool}> $entries */
        $entries = [];

        $add = function ($userId, string $bandName, bool $isSub) use (&$entries, $user) {
            $userId = (int) $userId;
            if ($userId === $user->id) {
                return;
            }
            $entries[$userId] ??= ['bands' => [], 'is_sub' => $isSub];
            if (!in_array($bandName, $entries[$userId]['bands'], true)) {
                $entries[$userId]['bands'][] = $bandName;
            }
            // Any non-sub relationship wins over sub.
            $entries[$userId]['is_sub'] = $entries[$userId]['is_sub'] && $isSub;
        };

        // Bands I own or play in: owners + members, plus that band's subs.
        foreach ($user->bands()->unique('id') as $band) {
            foreach ($band->owners()->pluck('user_id')->merge($band->members()->pluck('user_id')) as $id) {
                $add($id, $band->name, false);
            }
            foreach (DB::table('band_subs')->where('band_id', $band->id)->pluck('user_id') as $id) {
                $add($id, $band->name, true);
            }
        }

        // Bands I sub for: their owners and members (not fellow subs).
        foreach ($user->bandSub as $band) {
            foreach ($band->owners()->pluck('user_id')->merge($band->members()->pluck('user_id')) as $id) {
                $add($id, $band->name, false);
            }
        }

        $names = User::whereIn('id', array_keys($entries))->pluck('name', 'id');

        $contacts = collect($entries)
            ->map(function ($entry, $userId) use ($names) {
                $bandList = implode(', ', $entry['bands']);

                return [
                    'id'         => (int) $userId,
                    'name'       => (string) ($names[$userId] ?? ''),
                    'avatar_url' => null,
                    'context'    => $entry['is_sub'] ? 'Sub — ' . $bandList : $bandList,
                    'is_sub'     => $entry['is_sub'],
                ];
            })
            ->sortBy('name')->values();

        return response()->json(['contacts' => $contacts]);
    }

    /**
     * Conversation JSON — the one wire shape for a conversation everywhere.
     *
     * @param array{last: \Illuminate\Support\Collection, unread: \Illuminate\Support\Collection, dmOther: \Illuminate\Support\Collection} $prefetch
     *        Bulk-loaded data from prefetchSummaryData(), keyed by conversation_id.
     */
    private function summarize(Conversation $conversation, User $user, array $prefetch): array
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
            Conversation::TYPE_TOPIC => 'Thread',
            default => 'Conversation',
        };

        return [
            'id'                   => $conversation->id,
            'type'                 => $conversation->type,
            'band_id'              => $conversation->band_id ? (int) $conversation->band_id : null,
            'title'                => $title,
            'last_message_preview' => $preview,
            'last_message_at'      => $lastAt,
            'unread_count'         => $unread,
            'can_moderate'         => $user->can('moderate', $conversation),
        ];
    }

    /** GET /api/mobile/events/{event}/conversation */
    public function forEvent(Request $request, Events $event): JsonResponse
    {
        return $this->topicResponse($request, $this->conversations->topicFor($event));
    }

    /** GET /api/mobile/rehearsals/{rehearsal}/conversation */
    public function forRehearsal(Request $request, Rehearsal $rehearsal): JsonResponse
    {
        return $this->topicResponse($request, $this->conversations->topicFor($rehearsal));
    }

    /** GET /api/mobile/bands/{band}/bookings/{booking}/conversation */
    public function forBooking(Request $request, Bands $band, Bookings $booking): JsonResponse
    {
        return $this->topicResponse($request, $this->conversations->topicFor($booking));
    }

    private function topicResponse(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        // Opening a thread registers the viewer and marks it read.
        $this->conversations->touchParticipant($conversation, $request->user());

        return $this->threadPage($request, $conversation);
    }

    /**
     * The shared ThreadPage shape: also returned by the messages index
     * (Task 6). Messages come back oldest→newest; `channel` is what the
     * client subscribes to for live updates.
     */
    private function threadPage(Request $request, Conversation $conversation, ?int $before = null): JsonResponse
    {
        $user  = $request->user();
        $limit = 50;

        $page = $conversation->messages()->withTrashed()
            ->with(['user', 'attachments'])
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->latest('id')->limit($limit + 1)->get();

        $hasMore  = $page->count() > $limit;
        $messages = $page->take($limit)->reverse()->values()
            ->map(fn ($m) => $this->formatter->format($m));

        $participants = $conversation->participants()->with('user')->get()
            ->map(fn ($p) => [
                'user_id'      => (int) $p->user_id,
                'name'         => $p->user?->name,
                'avatar_url'   => null,
                'last_read_at' => $p->last_read_at?->toIso8601String(),
            ])->values();

        // Reuse the one Conversation JSON shape via summarize(). touchParticipant()
        // just ran, so unread_count is legitimately 0 — but the last-message
        // preview and timestamp are real.
        $ids       = collect([$conversation->id]);
        $lastReads = ConversationParticipant::where('user_id', $user->id)
            ->whereIn('conversation_id', $ids)
            ->pluck('last_read_at', 'conversation_id');
        $prefetch = $this->prefetchSummaryData($ids, $user, $lastReads);

        return response()->json([
            'conversation' => $this->summarize($conversation, $user, $prefetch),
            'messages'     => $messages,
            'participants' => $participants,
            'channel'      => 'private-conversation.' . $conversation->id,
            'has_more'     => $hasMore,
        ]);
    }

    /** GET /api/mobile/conversations/{conversation}/messages?before={messageId} — ThreadPage. */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return $this->threadPage(
            $request,
            $conversation,
            $request->filled('before') ? (int) $request->input('before') : null,
        );
    }

    /** POST /api/mobile/conversations/{conversation}/messages — multipart body and/or images[]. */
    public function storeMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('post', $conversation);

        $validated = $request->validate([
            'body'     => ['nullable', 'string', 'max:4000', 'required_without:images'],
            'images'   => ['nullable', 'array', 'max:4'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp,heic', 'max:10240'],
        ]);

        $user = $request->user();
        $disk = config('filesystems.default');

        // Store binaries BEFORE opening the transaction so the DB writes
        // (message + attachment rows) stay atomic; collect metadata first.
        $stored = [];
        foreach ($request->file('images', []) as $file) {
            $path = $file->storeAs(
                'chat/' . $conversation->id,
                Str::uuid() . '.' . $file->extension(),
                $disk,
            );
            $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];
            $stored[]   = [
                'path'       => $path,
                'disk'       => $disk,
                'mime'       => $file->getMimeType(),
                'width'      => $dimensions[0],
                'height'     => $dimensions[1],
                'size_bytes' => $file->getSize(),
            ];
        }

        try {
            $message = DB::transaction(function () use ($conversation, $user, $validated, $stored) {
                $message = $conversation->messages()->create([
                    'user_id' => $user->id,
                    'body'    => $validated['body'] ?? null,
                ]);

                foreach ($stored as $attributes) {
                    $message->attachments()->create($attributes);
                }

                return $message;
            });
        } catch (\Throwable $e) {
            // Rows rolled back — remove the just-stored blobs so none orphan.
            foreach ($stored as $attributes) {
                Storage::disk($attributes['disk'])->delete($attributes['path']);
            }

            throw $e;
        }

        // Sending implies having read everything up to your own message.
        $this->conversations->touchParticipant($conversation, $user);

        $message->load(['user', 'attachments']);

        return response()->json(['message' => $this->formatter->format($message)], 201);
    }

    /** POST /api/mobile/conversations/{conversation}/read {last_read_message_id} → 204. */
    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate(['last_read_message_id' => 'required|integer']);

        $message = $conversation->messages()->withTrashed()
            ->findOrFail($validated['last_read_message_id']);

        $participant = ConversationParticipant::firstOrCreate([
            'conversation_id' => $conversation->id,
            'user_id'         => $request->user()->id,
        ]);

        // Never move the marker backwards (out-of-order client calls).
        if (!$participant->last_read_at || $participant->last_read_at->lt($message->created_at)) {
            $participant->forceFill(['last_read_at' => $message->created_at])->save();
        }

        return response()->json(null, 204);
    }
}
