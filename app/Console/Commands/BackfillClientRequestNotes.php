<?php

namespace App\Console\Commands;

use App\Jobs\ParseClientRequestNotes;
use App\Models\BookingClientNotes;
use App\Models\Bookings;
use App\Models\QuestionnaireInstances;
use Illuminate\Console\Command;

/**
 * Queues a read of the questionnaire free text for upcoming bookings whose
 * answers were submitted before notes were stored per booking. Safe to run
 * again: bookings with stored notes are skipped, and the job itself skips
 * unchanged answers.
 */
class BackfillClientRequestNotes extends Command
{
    protected $signature = 'client-notes:backfill
                            {--all : Include past bookings}
                            {--dry-run : List the bookings without queueing}';

    protected $description = 'Queue AI parsing of questionnaire free-text requests for bookings without stored notes';

    public function handle(): int
    {
        if (!config('services.anthropic.key')) {
            $this->error('No Anthropic key configured; nothing to parse with.');

            return self::FAILURE;
        }

        $ids = Bookings::query()
            ->whereHas('questionnaireInstances', fn ($q) => $q->whereIn('status', [
                QuestionnaireInstances::STATUS_SUBMITTED,
                QuestionnaireInstances::STATUS_LOCKED,
            ]))
            ->whereNotIn('id', BookingClientNotes::select('booking_id'))
            ->when(!$this->option('all'), fn ($q) => $q->whereHas(
                'events',
                fn ($e) => $e->where('date', '>=', now()->toDateString()),
            ))
            ->pluck('id');

        foreach ($ids as $id) {
            if (!$this->option('dry-run')) {
                ParseClientRequestNotes::dispatch($id);
            }
        }

        $this->info(($this->option('dry-run') ? 'Would queue' : 'Queued') . " {$ids->count()} booking(s).");

        return self::SUCCESS;
    }
}
