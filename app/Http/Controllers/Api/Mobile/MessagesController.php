<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Events\ConversationStreamEvent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Services\Chat\MessageFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessagesController extends Controller
{
    public function __construct(private readonly MessageFormatter $formatter) {}

    /** PATCH /api/mobile/messages/{message} — author only, always. */
    public function update(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->user_id === $request->user()->id, 403, 'Only the author may edit a message.');

        $validated = $request->validate(['body' => 'required|string|max:4000']);

        $message->update(['body' => $validated['body'], 'edited_at' => now()]);
        $message->load(['user', 'attachments', 'reactions']);

        broadcast(new ConversationStreamEvent($message->conversation_id, 'message.updated', [
            'message' => $this->formatter->format($message),
        ]))->toOthers();

        return response()->json(['message' => $this->formatter->format($message)]);
    }

    /** DELETE /api/mobile/messages/{message} — author, or moderator on band/topic threads. */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        if ($message->user_id !== $user->id && !$user->can('moderate', $message->conversation)) {
            abort(403);
        }

        $message->delete();

        broadcast(new ConversationStreamEvent($message->conversation_id, 'message.deleted', [
            'message_id' => $message->id,
        ]))->toOthers();

        return response()->json(null, 204);
    }

    /** GET /api/mobile/messages/{message}/attachments/{attachment} — authenticated binary. */
    public function attachment(Message $message, MessageAttachment $attachment): StreamedResponse
    {
        // Route binding excludes soft-deleted messages, so a deleted
        // message's attachments 404 without an explicit check.
        abort_if($attachment->message_id !== $message->id, 404);
        $this->authorize('view', $message->conversation);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            null,
            ['Content-Type' => $attachment->mime],
        );
    }
}
