<?php

namespace App\Console\Commands;

use App\Models\Bands;
use App\Services\AdvanceReminderService;
use Illuminate\Console\Command;

class SendAdvanceReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:send-advance-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send weekly advance reminders to all bands';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending advance reminders...');

        $bands = Bands::all();

        if ($bands->isEmpty()) {
            $this->info('No bands found.');
            return 0;
        }

        foreach ($bands as $band) {
            $reminder = new AdvanceReminderService($band);
            $reminder->searchAndSend();

            $this->line("Processed advance reminders for: {$band->name}");
        }

        $this->info("Processed advance reminders for {$bands->count()} bands.");

        return 0;
    }
}
