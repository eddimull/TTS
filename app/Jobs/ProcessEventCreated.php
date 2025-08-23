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
            if($this->event->eventable->band->eventCalendar === null) {
                Log::info('No calendar ID found for band, skipping calendar update for event ID: ' . $this->event->id);
                return;
            }
            $calendarService = new CalendarService($this->event->eventable->band,'event', $this->event->eventable->band->eventCalendar);
            $calendarService->writeEventToCalendar($this->event);
        } catch (\Exception $e) {
            Log::error('Failed to update event in calendar: ' . $e->getMessage());
        }
    }
}
