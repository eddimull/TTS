<?php

namespace App\Jobs;

use App\Events\RehearsalPlannerStreamEvent;
use App\Models\RehearsalPlannerMessage;
use App\Models\RehearsalPlannerSession;
use App\Services\RehearsalPlannerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RehearsalPlannerTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Longer than the agent's 120s stream timeout so the queue worker does not
     * kill the job mid-stream.
     */
    public int $timeout = 130;

    /**
     * A streamed AI turn must not auto-retry/re-broadcast: a failed turn
     * is already marked 'failed' and the error event has been dispatched;
     * retrying would re-run the stream and re-broadcast on a stale placeholder.
     */
    public int $tries = 1;

    public function __construct(
        public int $sessionId,
        public int $assistantMessageId,
        public ?string $userText = null,
        public ?int $userMessageId = null,
    ) {}

    public function handle(RehearsalPlannerService $service): void
    {
        $session = RehearsalPlannerSession::find($this->sessionId);
        $assistant = RehearsalPlannerMessage::find($this->assistantMessageId);

        if (!$session || !$assistant) {
            return;
        }

        $service->runTurn($session, $assistant, $this->userText, $this->userMessageId);
    }

    /**
     * Handle job-level death (worker timeout, OOM, deserialization failure).
     *
     * If the job dies before the service's own catch block runs, the assistant
     * message is left in 'streaming' status and the client spins indefinitely.
     * This method ensures the message is marked 'failed' and an error event is
     * dispatched so the client can stop waiting.
     */
    public function failed(\Throwable $e): void
    {
        $assistant = RehearsalPlannerMessage::find($this->assistantMessageId);

        if (!$assistant || $assistant->status !== 'streaming') {
            return;
        }

        $assistant->update(['status' => 'failed']);

        RehearsalPlannerStreamEvent::dispatch($this->sessionId, 'error', [
            'message_id' => $this->assistantMessageId,
            'error'      => 'The planner failed to respond. Please retry.',
        ]);
    }
}
