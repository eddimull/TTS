<?php

namespace App\Jobs;

use App\Models\Bookings;
use App\Services\CalendarService;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessBookingCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $booking;

    public function __construct(Bookings $booking)
    {
        $this->booking = $booking;
    }

    public function handle()
    {
        try {
            if($this->booking->band->calendar_id === null) {
                Log::info('No calendar ID found for band, skipping calendar update for booking ID: ' . $this->booking->id);
                return;
            }
            $calendarService = new CalendarService($this->booking->band);
            $calendarService->writeBookingToCalendar($this->booking);
        } catch (\Exception $e) {
            Log::error('Failed to update booking in calendar: ' . $e->getMessage());
        }
    }
}
