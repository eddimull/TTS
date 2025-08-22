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

class ProcessBookingDeleted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $booking;

    public function __construct(Bookings $booking)
    {
        $this->booking = $booking;
        Log::info('Processing booking deletion for booking ID: ' . $booking->id);
    }

    public function handle()
    {
        try {
            if ($this->booking->band->bookingCalendar === null) {
                Log::info('No calendar ID found for band!, skipping calendar deletion for booking ID: ' . $this->booking->id);
                return;
            }

            $calendarService = new CalendarService($this->booking->band, 'booking');
            $calendarService->deleteBookingFromCalendar($this->booking);
        } catch (\Exception $e) {
            Log::error('Failed to delete booking from calendar in observer: ' . $e->getMessage());
        }
    }
}
