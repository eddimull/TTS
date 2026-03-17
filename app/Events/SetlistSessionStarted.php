<?php

namespace App\Events;

use App\Models\Events;
use App\Models\LiveSetlistSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class SetlistSessionStarted implements ShouldBroadcastNow
{
    public function __construct(
        public readonly LiveSetlistSession $session,
        public readonly Events $event,
        public readonly int $userId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'SetlistSessionStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'event_title' => $this->event->title,
            'event_key' => $this->event->key,
        ];
    }
}
