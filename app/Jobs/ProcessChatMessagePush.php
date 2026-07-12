<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
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
        $message = Message::with(['conversation.band', 'user'])->find($this->messageId);
        if (!$message || $message->trashed()) {
            return;
        }

        $conversation = $message->conversation;
        $body       = $message->body !== null && $message->body !== '' ? $message->body : '📷 Photo';
        $senderName = $message->user->name ?? 'Deleted user';
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

        foreach ($this->recipients($conversation) as $userId) {
            if ((int) $userId === (int) $message->user_id) {
                continue;
            }
            SendUserPush::dispatch((int) $userId, $data, 'chat_message:' . $message->id);
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
