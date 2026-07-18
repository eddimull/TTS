<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Events\ConversationStreamEvent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Services\Chat\MessageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Add/remove the caller's emoji reactions on a message.
 * Authorization: conversation participant (ConversationPolicy::view);
 * soft-deleted messages 404 via implicit binding. Both endpoints are
 * idempotent and return the message's aggregated reactions array.
 */
class MessageReactionsController extends Controller
{
    public function __construct(private MessageFormatter $formatter)
    {
    }

    /** POST /api/mobile/messages/{message}/reactions */
    public function store(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message->conversation);
        $data = $request->validate(['emoji' => ['required', 'string', 'max:16']]);

        $message->reactions()->firstOrCreate([
            'user_id' => $request->user()->id,
            'emoji' => $data['emoji'],
        ]);

        return $this->respondWithReactions($message);
    }

    /** DELETE /api/mobile/messages/{message}/reactions/{emoji} */
    public function destroy(Request $request, Message $message, string $emoji): JsonResponse
    {
        $this->authorize('view', $message->conversation);

        $message->reactions()
            ->where('user_id', $request->user()->id)
            ->where('emoji', $emoji)
            ->delete();

        return $this->respondWithReactions($message);
    }

    /** Re-format, stream the change to other open clients, return the aggregate. */
    private function respondWithReactions(Message $message): JsonResponse
    {
        $message->load(['user', 'attachments', 'reactions']);
        $formatted = $this->formatter->format($message);

        broadcast(new ConversationStreamEvent(
            $message->conversation_id,
            'message.updated',
            ['message' => $formatted],
        ))->toOthers();

        return response()->json(['reactions' => $formatted['reactions']]);
    }
}
