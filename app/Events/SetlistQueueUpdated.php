<?php

namespace App\Events;

use App\Models\LiveSetlistSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SetlistQueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LiveSetlistSession $session, public array $queue) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('setlist.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'queue' => $this->queue,
            'current_position' => $this->session->current_position,
        ];
    }
}
