<?php

namespace App\Observers;

use App\Jobs\ProcessBookingUpdated;
use App\Jobs\ProcessEventCreated;
use App\Jobs\ProcessEventDeleted;
use App\Jobs\ProcessEventUpdated;
use App\Models\Bookings;
use App\Models\Events;

class EventObserver
{
    public function created(Events $event)
    {
        \Log::info('Event created observer triggered for event ID: ' . $event->id);
        \Log::debug('Dispatching ProcessEventCreated job for event ID: ' . $event->id);
        ProcessEventCreated::dispatch($event);
        $this->resyncParentBooking($event);
    }

    public function updated(Events $event)
    {
        \Log::info('Event updated observer triggered for event ID: ' . $event->id);
        \Log::debug('Dispatching ProcessEventUpdated job for event ID: ' . $event->id);

        // Delay job by 2 seconds to allow rapid successive updates to settle
        // This batches rapid edits so the job processes the final state
        ProcessEventUpdated::dispatch($event, $event->getOriginal())->delay(now()->addSeconds(2));
        $this->resyncParentBooking($event);
    }

    public function deleted(Events $event)
    {
        \Log::info('Event deleted observer triggered for event ID: ' . $event->id);
        ProcessEventDeleted::dispatch($event);
        $this->resyncParentBooking($event);
    }

    /**
     * A booking's own Google Calendar entry derives its start/end/location
     * from its primary event but only re-syncs when the bookings row itself
     * changes. Event edits (mobile's only path for date changes) must
     * propagate upward or the booking's calendar entry stays on stale data.
     */
    private function resyncParentBooking(Events $event): void
    {
        $booking = $event->eventable;

        if (!$booking instanceof Bookings) {
            return;
        }

        // With no events left (last event deleted, or a booking-delete
        // cascade) there are no dates to derive the calendar entry from;
        // a resync would push null start/end to the Google API.
        if (!$booking->events()->exists()) {
            return;
        }

        ProcessBookingUpdated::dispatch($booking, $booking->getOriginal())
            ->delay(now()->addSeconds(2));
    }
}
