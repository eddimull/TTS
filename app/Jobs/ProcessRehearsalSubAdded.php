<?php

namespace App\Jobs;

use App\Mail\RehearsalSubAdded;
use App\Models\RehearsalSub;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class ProcessRehearsalSubAdded implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public RehearsalSub $sub,
        public int $actorId,
        public string $dedupeKey,
    ) {}

    public function handle(): void
    {
        $this->sub->loadMissing([
            'rehearsal.rehearsalSchedule.band',
            'rehearsal.events',
            'rehearsal.band',
            'bandRole',
            'user',
        ]);

        $rehearsal = $this->sub->rehearsal;
        $band = $rehearsal->rehearsalSchedule?->band ?? $rehearsal->band;
        if (!$band) {
            return;
        }

        $event = $rehearsal->events->first();
        $date  = $event
            ? (is_string($event->date) ? $event->date : $event->date->format('Y-m-d'))
            : null;

        Mail::to($this->sub->email)->send(
            new RehearsalSubAdded($this->sub, $rehearsal, $band, $date)
        );

        $user = $this->sub->user;
        if ($user && $user->deviceTokens()->exists()) {
            $whenText = $date ? Carbon::parse($date)->format('D, M j') : 'upcoming';

            $push = [
                'type'        => 'rehearsal_sub_added',
                'title'       => "You're invited to a rehearsal",
                'body'        => "{$band->name} · {$whenText}",
                'rehearsalId' => (string) $rehearsal->id,
            ];
            if ($date) {
                $push['date'] = $date;
            }

            SendUserPush::dispatch($user->id, $push, $this->dedupeKey, true);
        }
    }
}
