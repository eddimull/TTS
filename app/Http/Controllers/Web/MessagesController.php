<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Chat\ConversationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The web Messages inbox. The list/thread/DM/contacts JSON endpoints are the
 * mobile controllers under routes/chat.php; this class only renders the page
 * and serves the header badge count.
 */
class MessagesController extends Controller
{
    public function __construct(private readonly ConversationPresenter $presenter) {}

    /** GET /messages/{conversation?} */
    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        if ($conversation) {
            $this->authorize('view', $conversation);
        }

        return Inertia::render('Messages/Index', [
            'conversations'         => $this->presenter->listFor($request->user()),
            'initialConversationId' => $conversation?->id,
        ]);
    }

    /** GET /chat/unread-count → { count } */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->presenter->unreadTotalFor($request->user())]);
    }
}
