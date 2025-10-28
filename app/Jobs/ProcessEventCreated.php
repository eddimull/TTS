<?php

namespace App\Jobs;

use App\Models\Events;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessEventCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $event;

    public function __construct(Events $event)
    {
        $this->event = $event;
    }

    public function handle()
    {
        try {
            $event = $this->event->writeToGoogleCalendar($this->event->eventable->band->eventCalendar);
            $this->event->storeGoogleEventId($this->event->eventable->band->eventCalendar, $event->id);

            if($this->event->additional_data->public)
            {
                Log::info('Event is public, writing to public calendar for event ID: ' . $this->event->id);
                $publicEvent = $this->event->writeToGoogleCalendar($this->event->eventable->band->publicCalendar);
                $this->event->storeGoogleEventId($this->event->eventable->band->publicCalendar, $publicEvent->id);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update event in calendar: ' . $e->getMessage());
        }
    }
}
