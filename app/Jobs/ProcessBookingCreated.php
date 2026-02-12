<?php

namespace App\Jobs;

use App\Models\Bookings;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessBookingCreated implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $booking;

    public function __construct(Bookings $booking)
    {
        $this->booking = $booking;
    }

    public function uniqueId(): string
    {
        return 'booking-created-' . $this->booking->id;
    }

    public function handle()
    {
        Log::info('ProcessBookingCreated job started for booking ID: ' . $this->booking->id);

        $this->booking->refresh();
        Log::debug('Refreshed booking from database');

        try {
            $event = $this->booking->writeToGoogleCalendar($this->booking->band->bookingCalendar);
            Log::info('Created Google Calendar event with ID: ' . $event->id);
            $this->booking->storeGoogleEventId($this->booking->band->bookingCalendar, $event->id);
            Log::info('Created Google Events record for booking ID: ' . $this->booking->id);
        } catch (\Exception $e) {
            Log::error('Failed to update booking in calendar: ' . $e->getMessage());
        }
    }
}
