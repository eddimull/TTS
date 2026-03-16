<?php

namespace App\Events;

use App\Models\LiveSetlistSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SetlistSessionStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LiveSetlistSession $session) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('setlist.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->session->status,
            'current_position' => $this->session->current_position,
            'break_started_at' => $this->session->break_started_at?->toIso8601String(),
            'after_break' => (bool) $this->session->after_break,
        ];
    }
}
