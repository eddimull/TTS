<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Events\ConversationStreamEvent;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessChatMessagePush;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Events;
use App\Models\Rehearsal;
use App\Models\User;
use App\Services\Chat\ConversationPresenter;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConversationsController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly MessageFormatter $formatter,
        private readonly ConversationPresenter $presenter,
    ) {}

    /**
     * GET /api/mobile/conversations — the Messages screen: the user's DMs,
     * a band channel per owned/member band (lazily created so it is always
     * present), and every topic thread they can see that someone has actually
     * posted in.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['conversations' => $this->presenter->listFor($request->user())]);
    }

    /** POST /api/mobile/conversations/dm {user_id} — find-or-create the global pair thread. */
    public function storeDm(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $me    = $request->user();
        $other = User::findOrFail($validated['user_id']);

        abort_unless($this->conversations->canDm($me, $other), 403, 'You do not share a band with this user.');

        $conversation = $this->conversations->dmBetween($me, $other);

        // collect() (not null): the DM was just created or re-found; mobile's
        // established behaviour is the no-marker bucket here.
        $prefetch = $this->presenter->prefetch(collect([$conversation->id]), $me, collect());

        return response()->json(['conversation' => $this->presenter->summarize($conversation, $me, $prefetch)]);
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

        return response()->json($this->presenter->threadPage($request->user(), $conversation));
    }

    /** GET /api/mobile/conversations/{conversation}/messages?before={messageId} — ThreadPage. */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate(['before' => 'sometimes|integer|min:1']);

        return response()->json($this->presenter->threadPage(
            $request->user(),
            $conversation,
            $validated['before'] ?? null,
        ));
    }

    /** POST /api/mobile/conversations/{conversation}/messages — multipart body and/or images[]. */
    public function storeMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('post', $conversation);

        $validated = $request->validate([
            'body'     => ['nullable', 'string', 'max:4000', 'required_without:images'],
            'images'   => ['nullable', 'array', 'max:4'],
            // heic/heif deliberately excluded: PHP's getimagesize() can't
            // read HEIC, which would leave width/height null — but the
            // Flutter ChatAttachment contract types those as non-nullable
            // ints. The mobile client re-encodes camera images to jpeg
            // before upload anyway.
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
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

        $message->load(['user', 'attachments', 'reactions']);

        broadcast(new ConversationStreamEvent($conversation->id, 'message.created', [
            'message' => $this->formatter->format($message),
        ]))->toOthers();

        ProcessChatMessagePush::dispatch($message->id);

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
        // Broadcast only when the marker actually advances — duplicate or
        // out-of-order read POSTs must not re-emit conversation.read.
        if (!$participant->last_read_at || $participant->last_read_at->lt($message->created_at)) {
            $participant->forceFill(['last_read_at' => $message->created_at])->save();

            broadcast(new ConversationStreamEvent($conversation->id, 'conversation.read', [
                'user_id'      => $request->user()->id,
                'last_read_at' => $participant->last_read_at->toIso8601String(),
            ]))->toOthers();
        }

        return response()->json(null, 204);
    }

    /**
     * POST /api/mobile/conversations/delivered — bulk delivery ack.
     * "My app has received everything up to now": stamps last_delivered_at
     * on the caller's participant rows, but only for conversations holding a
     * message from someone else newer than the current stamp — routine
     * app-opens with nothing new write nothing and broadcast nothing.
     */
    public function delivered(Request $request): Response
    {
        $user = $request->user();
        $now = now();

        $rows = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->with('conversation')
            ->whereExists(function ($query) use ($user) {
                $query->selectRaw('1')
                    ->from('messages')
                    ->whereColumn('messages.conversation_id', 'conversation_participants.conversation_id')
                    ->where('messages.user_id', '!=', $user->id)
                    ->whereNull('messages.deleted_at')
                    ->where(function ($q) {
                        $q->whereNull('conversation_participants.last_delivered_at')
                            ->orWhereColumn('messages.created_at', '>', 'conversation_participants.last_delivered_at');
                    });
            })
            ->get();

        foreach ($rows as $participant) {
            // A participant row is not an access-control list (see
            // ConversationService::touchParticipant). A stale row — e.g. the
            // user was removed from the band owning the channel — must not be
            // stamped or broadcast for a conversation they can no longer view.
            if ($user->cannot('view', $participant->conversation)) {
                continue;
            }

            $participant->forceFill(['last_delivered_at' => $now])->save();
            broadcast(new ConversationStreamEvent($participant->conversation_id, 'conversation.delivered', [
                'user_id' => $user->id,
                'last_delivered_at' => $now->toIso8601String(),
            ]))->toOthers();
        }

        return response()->noContent();
    }

    /** POST /api/mobile/conversations/{conversation}/typing — ephemeral, nothing stored. */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('post', $conversation);

        broadcast(new ConversationStreamEvent($conversation->id, 'conversation.typing', [
            'user_id' => $request->user()->id,
            'name'    => $request->user()->name,
        ]))->toOthers();

        return response()->json(null, 204);
    }
}
