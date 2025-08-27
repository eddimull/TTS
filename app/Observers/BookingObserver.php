<?php

namespace App\Observers;

use App\Models\Bookings;
use App\Jobs\ProcessBookingCreated;
use App\Jobs\ProcessBookingDeleted;
use App\Jobs\ProcessBookingUpdated;


class BookingObserver
{
    public function created(Bookings $booking)
    {
        ProcessBookingCreated::dispatch($booking);
    }

    public function updated(Bookings $booking)
    {
        ProcessBookingUpdated::dispatch($booking, $booking->getOriginal());
    }

    public function deleted(Bookings $booking)
    {
        ProcessBookingDeleted::dispatch($booking);
    }
}
