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
        $this->writeToGoogleCalendar($this->event->getGoogleCalendar());

        if ($this->event->additional_data && $this->event->additional_data->public) {
            Log::info('Event is public, writing to public calendar for event ID: ' . $this->event->id);
            $this->writeToGoogleCalendar($this->event->getPublicGoogleCalendar());
        }

        $this->SendNotification();
    }

    public function writeToGoogleCalendar($calendar)
    {
        try {
            $event = $this->event->writeToGoogleCalendar($calendar);
            $this->event->storeGoogleEventId($calendar, $event->id);
        } catch (\Exception $e) {
            Log::error('Failed to update event in calendar: ' . $e->getMessage());
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
