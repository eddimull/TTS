<?php

namespace App\Services\Chat;

use App\Models\Message;

class MessageFormatter
{
    /**
     * Single FLAT wire shape for a message everywhere (thread page, stream).
     * Attachment binaries are fetched by the client-constructed URL
     * GET /api/mobile/messages/{message_id}/attachments/{id} — the payload
     * deliberately carries only what layout needs (id + dimensions).
     */
    public function format(Message $message): array
    {
        $deleted = $message->trashed();

        return [
            'id'              => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id'         => $message->user_id,
            'user_name'       => $message->user->name,
            'user_avatar_url' => null,
            'body'            => $deleted ? null : $message->body,
            'attachments'     => $deleted ? [] : $message->attachments->map(fn ($a) => [
                'id'     => $a->id,
                'width'  => $a->width,
                'height' => $a->height,
            ])->values()->all(),
            'edited_at'  => $message->edited_at?->toIso8601String(),
            'is_deleted' => $deleted,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
