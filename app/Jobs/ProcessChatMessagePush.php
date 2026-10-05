<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\CommentPosted;
use App\Notifications\DirectMessageReceived;
use App\Services\Chat\ConversationPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Resolves a message's audience ("push everything" per the spec) and fans
 * out one SendUserPush per recipient. Queued so the per-user permission
 * checks never sit on the send-message request path.
 */
class ProcessChatMessagePush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $message = Message::with(['conversation.band', 'conversation.conversable', 'user'])->find($this->messageId);
        if (!$message || $message->trashed()) {
            return;
        }

        $conversation = $message->conversation;
        $isTopic      = $conversation->type === Conversation::TYPE_TOPIC;
        $isDm         = $conversation->type === Conversation::TYPE_DM;
        $topicTitle   = $isTopic ? app(ConversationPresenter::class)->topicTitle($conversation) : null;
        $body       = $message->previewSnippet();
        $senderName = $message->senderDisplayName();
        $title      = $conversation->type === Conversation::TYPE_DM
            ? $senderName
            : ($conversation->band?->name ?? 'Band') . ' — ' . $senderName;

        // FCM data messages carry strings only; conversationId is stringified.
        $data = [
            'type'           => 'chat_message',
            'conversationId' => (string) $conversation->id,
            'title'          => $title,
            'body'           => $body,
        ];

        $recipientIds = $this->recipients($conversation);
        $users        = User::whereIn('id', $recipientIds)->get()->keyBy('id');

        foreach ($recipientIds as $userId) {
            if ((int) $userId === (int) $message->user_id) {
                continue;
            }
            // Web bell entries (database only), same audience as the push by
            // construction. Rule: topic threads → CommentPosted (here); DMs →
            // DirectMessageReceived (below); band channel chatter deliberately
            // stays off the bell — the Messages badge is its signal instead.
            if ($isTopic) {
                $users->get($userId)?->notify(new CommentPosted($message, $conversation, $topicTitle));
            }
            if ($isDm) {
                $users->get($userId)?->notify(new DirectMessageReceived($message, $conversation));
            }
            // alert: true routes through FcmSender::sendAlert() so a real APNs
            // notification block is sent. iOS never delivers data-only pushes to
            // a backgrounded app; the data map still rides along for tap routing.
            // The per-conversation Android tag keeps one tray slot per thread
            // (new messages replace, the app clears the slot on thread open) —
            // unbounded stacking also breaks tap deep-linking, because Android
            // auto-groups 4+ notifications and a group-summary tap carries no
            // message data to route on.
            SendUserPush::dispatch((int) $userId, $data, 'chat_message:' . $message->id, true, 'chat_' . $conversation->id);
        }
    }

    /** @return list<int> */
    private function recipients(Conversation $conversation): array
    {
        if ($conversation->type === Conversation::TYPE_DM) {
            return $conversation->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        }

        $band = $conversation->band;
        if (!$band) {
            return [];
        }

        $memberIds = $band->owners()->pluck('user_id')
            ->merge($band->members()->pluck('user_id'))
            ->unique();

        if ($conversation->type === Conversation::TYPE_BAND) {
            return $memberIds->map(fn ($id) => (int) $id)->values()->all();
        }

        // Topic: audience == everyone the policy admits. Reuse the policy so
        // recipients can never drift from visibility (incl. entitled subs).
        $subIds = \DB::table('band_subs')->where('band_id', $band->id)->pluck('user_id');

        return $memberIds->merge($subIds)->unique()
            ->filter(function ($userId) use ($conversation) {
                $user = User::find($userId);

                return $user && $user->can('view', $conversation);
            })
            ->map(fn ($id) => (int) $id)->values()->all();
    }
}
