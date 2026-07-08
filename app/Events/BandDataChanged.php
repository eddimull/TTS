<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Thin band-scoped change signal: tells subscribed band members that a model
 * changed so they can refetch through the API. Carries no model data — the
 * API layer stays the single permission-enforcing serializer.
 *
 * Queued (ShouldBroadcast, via Horizon) so broadcasting can never slow the
 * originating write; dispatched after commit so a client refetch can't read
 * pre-transaction state.
 */
class BandDataChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $bandId,
        public string $model,
        public int $id,
        public string $action,
        public ?array $parent = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('band.' . $this->bandId)];
    }

    public function broadcastAs(): string
    {
        return 'band.data-changed';
    }

    public function broadcastWith(): array
    {
        $payload = [
            'model'  => $this->model,
            'id'     => $this->id,
            'action' => $this->action,
        ];
        if ($this->parent !== null) {
            $payload['parent'] = $this->parent;
        }

        return $payload;
    }
}
