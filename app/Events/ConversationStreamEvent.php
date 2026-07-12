<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Full-payload live event for an OPEN conversation screen: instant message
 * append/edit/delete, read receipts, and typing — no refetch round-trip.
 * ShouldBroadcastNow: latency matters more than queue smoothing here.
 *
 * One class, FIVE wire events — broadcastAs() returns the type itself, so
 * clients bind to five distinct event names with no envelope:
 *   message.created {message} | message.updated {message}
 *   message.deleted {message_id} | conversation.read {user_id, last_read_at}
 *   conversation.typing {user_id, name}
 */
class ConversationStreamEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string,mixed> $data */
    public function __construct(
        public int $conversationId,
        public string $type,
        public array $data = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.' . $this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return $this->type;
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
