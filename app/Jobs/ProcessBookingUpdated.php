<?php

namespace App\Jobs;

use App\Models\Bookings;
use App\Notifications\TTSNotification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessBookingUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $booking;
    protected $originalData;

    public function __construct(Bookings $booking, array $originalData)
    {
        $this->booking = $booking;
        $this->originalData = $originalData;
    }

    public function handle()
    {
        Log::info('Processing booking update for booking ID: ' . $this->booking->id);
        // Update calendar event
        $this->writeToGoogleCalendar($this->booking->band->bookingCalendar);
        $this->SendNotification();
    }

    public function writeToGoogleCalendar($calendar)
    {
        if (!$calendar) {
            Log::warning("No calendar provided for booking ID: {$this->booking->id}");
            return;
        }

        try {
            // Check if booking already exists
            $existingGoogleEvent = $this->booking->getGoogleEvent($calendar);

            if ($existingGoogleEvent) {
                Log::info("Updating existing Google Calendar event for Booking ID: {$this->booking->id}, Google Event ID: {$existingGoogleEvent->google_event_id}, Calendar: {$calendar->type}");
            } else {
                Log::info("Creating new Google Calendar event for Booking ID: {$this->booking->id}, Calendar: {$calendar->type}");
            }

            // Write to Google Calendar (trait handles update vs insert)
            $event = $this->booking->writeToGoogleCalendar($calendar);

            if (!$event || !$event->id) {
                throw new \Exception("Google Calendar API returned invalid event");
            }

            // Store the relationship
            $this->booking->storeGoogleEventId($calendar, $event->id);

            Log::info("Successfully synced Booking ID: {$this->booking->id} with Google Event ID: {$event->id}, Calendar: {$calendar->type}");

        } catch (\Exception $e) {
            Log::error("Failed to sync Booking ID: {$this->booking->id} to calendar: " . $e->getMessage(), [
                'booking_id' => $this->booking->id,
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
        $newStatus = $this->booking->status;

        if ($oldStatus && $oldStatus !== $newStatus) {
            $notificationData = [
                'text' => "Booking '{$this->booking->name}' status changed from {$oldStatus} to {$newStatus}",
                'emailHeader' => "Booking Status Update for {$this->booking->band->name}",
                'actionText' => 'View Booking',
                'route' => 'Booking Details',
                'routeParams' => [
                    'band' => $this->booking->band_id,
                    'booking' => $this->booking->id
                ],
                'url' => "/bands/{$this->booking->band_id}/booking/{$this->booking->id}"
            ];

            // Get all band members and owners
            $band = $this->booking->band;
            $bandMembers = $band->everyone();

            // Notify each member
            foreach ($bandMembers as $member) {
                $user = $member->user;
                if ($user) {
                    $user->notify(new TTSNotification($notificationData));
                }
            }

            // Also notify the booking author if they're not already in the band
            if ($this->booking->author && !$bandMembers->contains('user_id', $this->booking->author_id)) {
                $this->booking->author->notify(new TTSNotification($notificationData));
            }
        }
    }
}
