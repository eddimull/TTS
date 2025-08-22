<?php

namespace App\Jobs;

use App\Models\Bookings;
use App\Services\CalendarService;
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
        // Update calendar event
        try {
            if($this->booking->band->bookingCalendar === null) {
                Log::info('No calendar ID found for band, skipping calendar update for booking ID: ' . $this->booking->id);
            } else {
                $calendarService = new CalendarService($this->booking->band, 'booking');
                Log::info('Updating calendar for booking ID: ' . $this->booking->id);
                $calendarService->writeBookingToCalendar($this->booking);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update booking in calendar: ' . $e->getMessage());
        }

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
