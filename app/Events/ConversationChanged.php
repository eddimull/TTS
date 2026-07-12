<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Thin per-user change signal for DM conversations — the DM analogue of
 * BandDataChanged (DMs have no band channel to ride). One event per
 * participant, on their standard App.Models.User.{id} private channel.
 */
class ConversationChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $conversationId,
        public int $messageId,
        public string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.data-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'model'  => 'message',
            'id'     => $this->messageId,
            'action' => $this->action,
            'parent' => ['model' => 'conversation', 'id' => $this->conversationId],
        ];
    }
}
