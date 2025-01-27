<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\BandEvents;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\BookingContacts;
use App\Models\Events;
use Carbon\Carbon;

class trimBookingNotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'etl:trim-booking-notes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trim excess whitespace from booking notes';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting trimming of booking notes...');

        // mysql regex is a bit funky. [:space:] is the same as \s in php regex, but it must be inside a bracketed group
        DB::statement("UPDATE bookings SET notes = REGEXP_REPLACE(notes, '^[[:space:]]+|[[:space:]]+$', '')");

        $this->info('Booking notes trimmed successfully!');
    }
}
