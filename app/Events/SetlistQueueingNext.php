<?php

namespace App\Events;

use App\Models\LiveSetlistSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class SetlistQueueingNext implements ShouldBroadcastNow
{
    public function __construct(public readonly LiveSetlistSession $session) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('setlist.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
