<?php

namespace App\Jobs;

use App\Models\Events;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessEventCreated implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $event;

    public function __construct(Events $event)
    {
        $this->event = $event;
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'event-created-' . $this->event->id;
    }

    public function handle()
    {
        Log::info('ProcessEventCreated job started for event ID: ' . $this->event->id);

        // CRITICAL: Refresh the event from the database to get the latest changes
        // This ensures we sync the CURRENT state, not stale data from when the job was dispatched
        $this->event->refresh();
        Log::debug('Refreshed event from database, current title: ' . ($this->event->title ?? 'N/A'));

        try {
            $event = $this->event->writeToGoogleCalendar($this->event->getGoogleCalendar());
            if ($event) {
                $this->event->storeGoogleEventId($this->event->getGoogleCalendar(), $event->id);
            }

            if($this->event->additional_data && $this->event->additional_data->public)
            {
                Log::info('Event is public, writing to public calendar for event ID: ' . $this->event->id);
                $publicEvent = $this->event->writeToGoogleCalendar($this->event->getPublicGoogleCalendar());
                if ($publicEvent) {
                    $this->event->storeGoogleEventId($this->event->getPublicGoogleCalendar(), $publicEvent->id);
                }
            }

            // Calculate distances for all band members
            CalculateEventDistances::dispatch($this->event);
        } catch (\Exception $e) {
            Log::error('Failed to create event in calendar: ' . $e->getMessage());
        }
    }
}
