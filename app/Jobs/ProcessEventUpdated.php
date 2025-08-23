<?php

namespace App\Jobs;

use App\Models\Bookings;
use App\Models\BandEvents;
use App\Models\Events;
use App\Services\CalendarService;
use Illuminate\Support\Facades\Log;
use App\Notifications\TTSNotification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessEventUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $event;
    protected $originalData;

    public function __construct(Events $event, array $originalData)
    {
        Log::info('Processing event update for event ID: ' . $event->id);
        $this->event = $event;
        $this->originalData = $originalData;
    }

    public function handle()
    {
        // Update calendar event
        try {
            if($this->event->eventable->band->eventCalendar === null) {
                Log::info('No calendar ID found for eventable, skipping calendar update for event ID: ' . $this->event->id);
            } else {
                $calendarService = new CalendarService($this->event->eventable->band, 'event');
                Log::info('Updating calendar for event ID: ' . $this->event->id);
                $calendarService->writeEventToCalendar($this->event);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update event in calendar: ' . $e->getMessage());
        }

        // Handle status change notifications
        $oldStatus = $this->originalData['status'] ?? null;
        $newStatus = $this->event->status;

        if ($oldStatus && $oldStatus !== $newStatus) {
            $notificationData = [
                'text' => "Event '{$this->event->name}' status changed from {$oldStatus} to {$newStatus}",
                'emailHeader' => "Event Status Update for {$this->event->band->name}",
                'actionText' => 'View Event',
                'route' => 'Event Details',
                'routeParams' => [
                    'band' => $this->event->band_id,
                    'event' => $this->event->id
                ],
                'url' => "/bands/{$this->event->band_id}/events/{$this->event->id}"
            ];

            // Get all band members and owners
            $band = $this->event->band;
            $bandMembers = $band->everyone();

            // Notify each member
            foreach ($bandMembers as $member) {
                $user = $member->user;
                if ($user) {
                    $user->notify(new TTSNotification($notificationData));
                }
            }

            // Also notify the event author if they're not already in the band
            if ($this->event->author && !$bandMembers->contains('user_id', $this->event->author_id)) {
                $this->event->author->notify(new TTSNotification($notificationData));
            }
        }
    }
}
