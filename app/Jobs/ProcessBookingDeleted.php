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

class ProcessBookingDeleted implements ShouldQueue
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
            $calendarService = new CalendarService($this->booking->band);
            $calendarService->deleteBookingFromCalendar($this->booking);
        } catch (\Exception $e) {
            Log::error('Failed to delete booking from calendar in observer: ' . $e->getMessage());
        }
    }
}
