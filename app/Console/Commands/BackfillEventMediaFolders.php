<?php

namespace App\Console\Commands;

use App\Models\Events;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillEventMediaFolders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:backfill-event-folders
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate media folder paths for existing events that don\'t have them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('Backfilling media folder paths for existing events...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        $this->newLine();

        // Get all events without media_folder_path
        $events = Events::whereNull('media_folder_path')->get();

        if ($events->isEmpty()) {
            $this->info('No events found without media_folder_path. All events are up to date!');
            return 0;
        }

        $this->info("Found {$events->count()} events without folder paths.");
        $this->newLine();

        $usedPaths = [];
        $updated = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($events->count());
        $progressBar->start();

        foreach ($events as $event) {
            $progressBar->advance();

            try {
                // Generate folder path: Year/Month/Event-Name
                $year = $event->date->format('Y');
                $month = $event->date->format('m');

                // Sanitize event title for folder name
                $eventName = $this->sanitizeEventName($event);
                $slug = Str::slug($eventName);

                // Handle duplicate slugs in same month
                $basePath = "{$year}/{$month}";
                $fullPath = "{$basePath}/{$slug}";
                $counter = 1;

                while (in_array($fullPath, $usedPaths)) {
                    $fullPath = "{$basePath}/{$slug}-{$counter}";
                    $counter++;
                }

                $usedPaths[] = $fullPath;

                if (!$dryRun) {
                    $event->update(['media_folder_path' => $fullPath]);
                }

                $updated++;

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to process event #{$event->id}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Backfill Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Total', $events->count()],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->newLine();
            $this->info('✓ All events have been updated with media folder paths!');
        }

        return 0;
    }

    /**
     * Sanitize event name for folder naming
     */
    private function sanitizeEventName(Events $event): string
    {
        // Use event title if available
        if (!empty($event->title)) {
            return Str::limit($event->title, 50, '');
        }

        // Fallback to event ID
        return "Event-{$event->id}";
    }
}
