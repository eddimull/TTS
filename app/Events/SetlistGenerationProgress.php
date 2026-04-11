<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SetlistGenerationProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $eventKey,
        public string $step,
        public string $status,   // 'working' | 'done' | 'error'
        public ?string $detail = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->userId)];
    }

    public function broadcastWith(): array
    {
        return [
            'event_key' => $this->eventKey,
            'step'      => $this->step,
            'status'    => $this->status,
            'detail'    => $this->detail,
        ];
    }
}
