<?php

namespace App\Jobs;

use App\Models\Rehearsal;
use App\Notifications\RehearsalCancelled;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProcessRehearsalCancelled implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Rehearsal $rehearsal,
        public int $actorId,
        public bool $isCancelled,
        public string $dedupeKey,
    ) {}

    public function handle(): void
    {
        $this->rehearsal->loadMissing(['rehearsalSchedule.band', 'events', 'band']);
        $band = $this->rehearsal->rehearsalSchedule?->band ?? $this->rehearsal->band;
        if (!$band) {
            return;
        }

        $event = $this->rehearsal->events->first();
        $date = $event
            ? (is_string($event->date) ? $event->date : $event->date->format('Y-m-d'))
            : null;

        // Re-render the Google Calendar entry so the cancelled (or restored)
        // state shows up: red + "Cancelled: " prefix comes from the model's
        // calendar representation methods. Current status is passed as the
        // original so the job's status-change notification stays quiet.
        if ($event) {
            ProcessEventUpdated::dispatch($event, ['status' => $event->status]);
        }

        $name = $this->rehearsal->rehearsalSchedule?->name ?? 'Rehearsal';
        $whenText = $date ? Carbon::parse($date)->format('D, M j') : 'upcoming';

        $push = [
            'type'        => $this->isCancelled ? 'rehearsal_cancelled' : 'rehearsal_restored',
            'title'       => $this->isCancelled ? 'Rehearsal cancelled' : 'Rehearsal back on',
            'body'        => "{$name} · {$whenText}",
            'rehearsalId' => (string) $this->rehearsal->id,
        ];
        if ($date) {
            $push['date'] = $date;
        }

        $notifiedUserIds = [];

        foreach ($band->everyone() as $member) {
            $user = $member->user;
            if (!$user || $user->id === $this->actorId) {
                continue;
            }
            if (in_array($user->id, $notifiedUserIds, true)) {
                continue;
            }
            $notifiedUserIds[] = $user->id;

            $user->notify(new RehearsalCancelled($this->rehearsal, $this->isCancelled, $date));

            if ($user->deviceTokens()->exists()) {
                SendUserPush::dispatch($user->id, $push, $this->dedupeKey, true);
            }
        }
    }
}
