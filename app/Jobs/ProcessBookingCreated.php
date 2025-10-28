<?php

namespace App\Jobs;

use App\Models\Bookings;
use App\Models\GoogleEvents;
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
            Log::info('Processing booking creation for booking ID: ' . $this->booking->id);
            $event = $this->booking->writeToGoogleCalendar($this->booking->band->bookingCalendar);
            Log::info('Created Google Calendar event with ID: ' . $event->id);
            $this->booking->storeGoogleEventId($this->booking->band->bookingCalendar, $event->id);
            Log::info('Created Google Events record for booking ID: ' . $this->booking->id);
        } catch (\Exception $e) {
            Log::error('Failed to update booking in calendar: ' . $e->getMessage());
        }
    }
}
