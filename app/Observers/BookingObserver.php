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

        if($booking->status === 'cancelled')
        {
            $this->deleteBookingEvents($booking);
        }
    }


    public function deleting(Bookings $booking)
    {
        \Log::info("Deleting booking ID: {$booking->id}, removing {$booking->events()->count()} associated events");

        // Delete all associated events
        // Each event deletion will trigger EventObserver::deleted
        // which dispatches ProcessEventDeleted to remove from Google Calendar
        // otherwise you'll have dangling calendar items
        $this->deleteBookingEvents($booking);
    }

    private function deleteBookingEvents(Bookings $booking)
    {
        foreach ($booking->events as $event) {
            $event->delete();
        }
    }

    public function deleted(Bookings $booking)
    {
        ProcessBookingDeleted::dispatch($booking);
    }
}
