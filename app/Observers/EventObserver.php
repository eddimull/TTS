<?php

namespace App\Observers;

use App\Jobs\ProcessEventCreated;
use App\Jobs\ProcessEventDeleted;
use App\Jobs\ProcessEventUpdated;
use App\Models\Events;

class EventObserver
{
    public function created(Events $event)
    {
        \Log::info('Event created observer triggered for event ID: ' . $event->id);
        \Log::debug('Dispatching ProcessEventCreated job for event ID: ' . $event->id);
        ProcessEventCreated::dispatch($event);
    }

    public function updated(Events $event)
    {
        \Log::info('Event updated observer triggered for event ID: ' . $event->id);
        \Log::debug('Dispatching ProcessEventUpdated job for event ID: ' . $event->id);

        // Delay job by 2 seconds to allow rapid successive updates to settle
        // This batches rapid edits so the job processes the final state
        ProcessEventUpdated::dispatch($event, $event->getOriginal())->delay(now()->addSeconds(2));
    }

    public function deleted(Events $event)
    {
        \Log::info('Event deleted observer triggered for event ID: ' . $event->id);
        ProcessEventDeleted::dispatch($event);
    }
}
