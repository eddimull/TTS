<?php

namespace App\Jobs;

use App\Models\Events;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessEventDeleted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $event;

    public function __construct(Events $event)
    {
        $this->event = $event;
        Log::info('Processing event deletion for event ID: ' . $event->id);
    }

    public function handle()
    {
        try {
            $this->event->deleteFromGoogleCalendar($this->event->eventable->band->eventCalendar);
            Log::info('Deleted event from calendar in observer for event ID: ' . $this->event->id);
            if($this->event->additional_data->public)
            {
                Log::info('Event is public, deleting from public calendar for event ID: ' . $this->event->id);
                $this->event->deleteFromGoogleCalendar($this->event->eventable->band->publicCalendar);
                Log::info('Deleted public event from calendar in observer for event ID: ' . $this->event->id);
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete event from calendar in observer: ' . $e->getMessage());
        }
    }
}
