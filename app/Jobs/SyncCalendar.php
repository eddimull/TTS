<?php

namespace App\Jobs;

use App\Models\BandCalendars;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SyncCalendar implements ShouldQueue
{
    use Queueable;

    public $timeout = 1200; //10 min. This can take awhile.
    protected BandCalendars $calendar;

    /**
     * Create a new job instance.
     */
    public function __construct(BandCalendars $calendar)
    {
        $this->calendar = $calendar;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $allItems = $this->retrieveItems();

        foreach ($allItems as $item) {
            try{
                \Log::info('Syncing event: ' . $item->id . ' to Google Calendar');
                $event = $item->writeToGoogleCalendar($this->calendar);
                $item->storeGoogleEventId($this->calendar, $event->id);
            } catch (\Exception $e) {
                \Log::error('Error syncing event: ' . $item->id . ' - ' . $e->getMessage());
            }
        }
    }

    public function retrieveItems(): Collection
    {
        //Hmmm...
        ini_set('memory_limit', '2048M');
        switch ($this->calendar->type) {
            case 'event':
                return $this->calendar->band->events;
            case 'public':
                return $this->calendar->band->futurePublicEvents;
            case 'booking':
                return $this->calendar->band->bookings;
            default:
                return collect();
        }
    }
}
