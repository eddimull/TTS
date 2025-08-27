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
        ProcessEventCreated::dispatch($event);
    }

    public function updated(Events $event)
    {
        \Log::info('Event updated observer triggered for event ID: ' . $event->id);
        ProcessEventUpdated::dispatch($event, $event->getOriginal());
    }

    public function deleted(Events $event)
    {
        \Log::info('Event deleted observer triggered for event ID: ' . $event->id);
        ProcessEventDeleted::dispatch($event);
    }
}
