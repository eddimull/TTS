<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Notifications\Notification;

/**
 * Web bell entry for a new direct message. Database-only (no mail) and
 * shaped for Layouts/Authenticated.vue (`text`, `route`, `routeParams`);
 * the link opens the inbox with this conversation selected.
 */
class DirectMessageReceived extends Notification
{
    public function __construct(
        public readonly Message $message,
        public readonly Conversation $conversation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $snippet = $this->message->previewSnippet(80);
        $sender  = $this->message->senderDisplayName();

        return [
            'text'            => "{$sender}: {$snippet}",
            'route'           => 'messages.index',
            'routeParams'     => ['conversation' => $this->conversation->id],
            'conversation_id' => $this->conversation->id,
            'message_id'      => $this->message->id,
        ];
    }
}
