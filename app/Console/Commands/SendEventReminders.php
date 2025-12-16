<?php

namespace App\Console\Commands;

use App\Mail\EventReminder;
use App\Models\BandEvents;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails for events happening today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending event reminders for today...');

        $bandEvents = BandEvents::whereDate('event_time', Carbon::today())->get();

        if ($bandEvents->isEmpty()) {
            $this->info('No events scheduled for today.');
            return 0;
        }

        $sentCount = 0;

        foreach ($bandEvents as $event) {
            $band = $event->band;
            $owners = $band->owners;
            $members = $band->members;

            // Send to owners
            foreach ($owners as $person) {
                $member = $person->user;
                Mail::to($member->email)->send(new EventReminder($event));
                $sentCount++;
            }

            // Send to members
            foreach ($members as $person) {
                $member = $person->user;
                Mail::to($member->email)->send(new EventReminder($event));
                $sentCount++;
            }

            $this->line("Sent reminders for: {$event->event_name}");
        }

        $this->info("Sent {$sentCount} event reminder emails for {$bandEvents->count()} events.");

        return 0;
    }
}
