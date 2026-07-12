<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $lastReads = ConversationParticipant::where('user_id', $user->id)
            ->whereIn('conversation_id', $all->pluck('id'))
            ->pluck('last_read_at', 'conversation_id');

        $rows = $all->map(fn (Conversation $c) => $this->summarize($c, $user, $lastReads->get($c->id)))
            ->sortByDesc(fn ($row) => $row['last_message_at'] ?? '')
            ->values();

        return response()->json(['conversations' => $rows]);
    }

    /** POST /api/mobile/conversations/dm {user_id} — find-or-create the global pair thread. */
    public function storeDm(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $me    = $request->user();
        $other = User::findOrFail($validated['user_id']);

        abort_unless($this->conversations->canDm($me, $other), 403, 'You do not share a band with this user.');

        $conversation = $this->conversations->dmBetween($me, $other);

        return response()->json(['conversation' => $this->summarize($conversation, $me, null)]);
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

    /** Conversation JSON — the one wire shape for a conversation everywhere. */
    private function summarize(Conversation $conversation, User $user, $lastReadAt): array
    {
        $last = $conversation->messages()->withTrashed()->latest('id')->with('attachments')->first();

        $preview = null;
        $lastAt  = null;
        if ($last) {
            $lastAt  = $last->created_at->toIso8601String();
            $preview = $last->trashed()
                ? null
                : (($last->body !== null && $last->body !== '') ? $last->body : '📷 Photo');
        }

        $unread = Message::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
            ->count();

        $title = match ($conversation->type) {
            Conversation::TYPE_BAND => $conversation->band?->name ?? 'Band',
            Conversation::TYPE_DM   => $conversation->participants()
                ->where('user_id', '!=', $user->id)->with('user')->first()?->user?->name ?? 'Direct message',
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
}
