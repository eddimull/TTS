<?php

namespace App\Console\Commands;

use App\Models\Events;
use App\Jobs\CalculateEventDistances;
use Illuminate\Console\Command;

class CalculateEventDistancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:calculate-distances 
                            {--band= : Only calculate for a specific band ID}
                            {--event= : Only calculate for a specific event ID}
                            {--force : Force recalculation even if already calculated}
                            {--queue : Queue the jobs instead of running synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate travel distances for all band members for events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting event distance calculations...');

        // Build query
        $query = Events::query()->with('eventable');

        // Filter by specific event
        if ($eventId = $this->option('event')) {
            $query->where('id', $eventId);
        }

        // Filter by band if specified
        if ($bandId = $this->option('band')) {
            $query->whereHasMorph('eventable', ['App\Models\Bookings', 'App\Models\BandEvents'], function ($q) use ($bandId) {
                $q->where('band_id', $bandId);
            });
        }

        $events = $query->get();
        $totalEvents = $events->count();

        if ($totalEvents === 0) {
            $this->warn('No events found to process.');
            return 0;
        }

        $this->info("Found {$totalEvents} events to process.");

        $progressBar = $this->output->createProgressBar($totalEvents);
        $progressBar->start();

        $processed = 0;
        $queued = 0;
        $useQueue = $this->option('queue');

        foreach ($events as $event) {
            if ($useQueue) {
                // Dispatch to queue
                CalculateEventDistances::dispatch($event);
                $queued++;
            } else {
                // Run synchronously
                $job = new CalculateEventDistances($event);
                $job->handle();
                $processed++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($useQueue) {
            $this->info("✓ Successfully queued {$queued} distance calculation jobs.");
            $this->comment("Jobs are being processed in the background. Check logs for completion status.");
        } else {
            $this->info("✓ Successfully processed {$processed} events.");
            $this->comment("Distance calculations have been stored in the database.");
        }

        return 0;
    }
}
