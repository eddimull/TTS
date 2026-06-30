<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RehearsalPlannerStreamEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string,mixed> $data */
    public function __construct(
        public int $sessionId,
        public string $type,   // 'text_delta' | 'done' | 'error'
        public array $data = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('rehearsal-planner.' . $this->sessionId)];
    }

    public function broadcastAs(): string
    {
        return 'planner.stream';
    }

    public function broadcastWith(): array
    {
        return array_merge(['type' => $this->type], $this->data);
    }
}
