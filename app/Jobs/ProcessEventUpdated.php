<?php

namespace App\Jobs;

use App\Models\Bookings;
use App\Models\BandEvents;
use App\Models\Events;
use Illuminate\Support\Facades\Log;
use App\Notifications\TTSNotification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessEventUpdated implements ShouldQueue, ShouldBeUniqueUntilProcessing
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

    public function uniqueId(): string
    {
        return 'event-updated-' . $this->event->id;
    }

    public function handle()
    {
        Log::info('ProcessEventUpdated job started for event ID: ' . $this->event->id);

        $this->event->refresh();
        Log::debug('Refreshed event from database, current title: ' . ($this->event->title ?? 'N/A'));

        $this->writeToGoogleCalendar($this->event->getGoogleCalendar());

        if ($this->event->additional_data && is_object($this->event->additional_data) && isset($this->event->additional_data->public) && $this->event->additional_data->public) {
            Log::info('Event is public, writing to public calendar for event ID: ' . $this->event->id);
            $this->writeToGoogleCalendar($this->event->getPublicGoogleCalendar());
        }

        CalculateEventDistances::dispatch($this->event);

        $this->SendNotification();
    }

    public function writeToGoogleCalendar($calendar)
    {
        if (!$calendar) {
            Log::warning("No calendar provided for event ID: {$this->event->id}");
            return;
        }

        try {
            $existingGoogleEvent = $this->event->getGoogleEvent($calendar);

            if ($existingGoogleEvent) {
                Log::info("Updating existing Google Calendar event for Event ID: {$this->event->id}, Google Event ID: {$existingGoogleEvent->google_event_id}, Calendar: {$calendar->type}");
            } else {
                Log::info("Creating new Google Calendar event for Event ID: {$this->event->id}, Calendar: {$calendar->type}");
            }

            $event = $this->event->writeToGoogleCalendar($calendar);

            if (!$event || !$event->id) {
                throw new \Exception("Google Calendar API returned invalid event");
            }

            $this->event->storeGoogleEventId($calendar, $event->id);

            Log::info("Successfully synced Event ID: {$this->event->id} with Google Event ID: {$event->id}, Calendar: {$calendar->type}");

        } catch (\Exception $e) {
            Log::error("Failed to sync Event ID: {$this->event->id} to calendar: " . $e->getMessage(), [
                'event_id' => $this->event->id,
                'calendar_id' => $calendar->id ?? 'unknown',
                'calendar_type' => $calendar->type ?? 'unknown',
                'exception' => $e->getTraceAsString()
            ]);

            // Re-throw critical errors
            throw $e;
        }
    }

    public function SendNotification()
    {   
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
