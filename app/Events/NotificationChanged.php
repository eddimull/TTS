<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Thin per-user signal that the notification feed changed — the bell
 * analogue of ConversationChanged. Clients refetch the feed/badge; nothing
 * is carried beyond ids. Not toOthers(): the acting device refreshes too.
 */
class NotificationChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public ?string $notificationId,
        public string $action, // created | read | seen
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
        return ['model' => 'notification', 'id' => $this->notificationId, 'action' => $this->action];
    }
}
