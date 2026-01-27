<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Horizon metrics snapshot (every 5 minutes)
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        // Check for signed PandaDoc contracts every day in case the webhook failed
        $schedule->command('contracts:check-signed')
            ->dailyAt('11:00');
            

        // Send daily event reminders at 9:00 AM
        $schedule->command('events:send-reminders')
            ->dailyAt('9:00');

        // Send weekly advance reminders on Tuesday at 11:00 PM
        $schedule->command('events:send-advance-reminders')
            ->weeklyOn(2, '23:00');

        // Send deposit payment reminders daily at 10:00 AM
        $schedule->command('payments:send-deposit-reminders')
            ->dailyAt('10:00');

        // Send final payment reminders daily at 10:00 AM
        $schedule->command('payments:send-final-reminders')
            ->dailyAt('10:00');

        // Sync all Google Drive folders every 6 hours
        $schedule->job(new \App\Jobs\SyncAllGoogleDriveFolders())
            ->everySixHours()
            ->onOneServer();

        // Clean up stale chunked uploads older than 24 hours
        $schedule->job(new \App\Jobs\CleanupStaleChunkedUploads())
            ->hourly()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
