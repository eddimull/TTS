<?php

namespace App\Events;

use App\Models\LiveSetlistSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SetlistCaptainChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveSetlistSession $session,
        public int $userId,
        public string $action, // 'promoted' | 'demoted'
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('setlist.' . $this->session->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'action' => $this->action,
        ];
    }
}
