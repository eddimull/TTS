<?php

namespace App\Jobs;

use App\Models\Events;
use App\Services\CalendarService;
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
            if ($this->event->eventable->band->eventCalendar === null) {
                Log::info('No calendar ID found for band!, skipping calendar deletion for event ID: ' . $this->event->id);
                return;
            }

            $calendarService = new CalendarService($this->event->eventable->band, 'event');
            $calendarService->deleteEventFromCalendar($this->event);
        } catch (\Exception $e) {
            Log::error('Failed to delete event from calendar in observer: ' . $e->getMessage());
        }
    }
}
