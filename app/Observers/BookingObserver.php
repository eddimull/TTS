<?php

namespace App\Observers;

use App\Models\Bookings;
use App\Notifications\TTSNotification;

class BookingObserver
{
    public function updated(Bookings $booking)
    {
        if ($booking->isDirty('status'))
        {
            $oldStatus = $booking->getOriginal('status');
            $newStatus = $booking->status;

            // Prepare notification data
            $notificationData = [
                'text' => "Booking '{$booking->name}' status changed from {$oldStatus} to {$newStatus}",
                'emailHeader' => "Booking Status Update for {$booking->band->name}",
                'actionText' => 'View Booking',
                'route' => 'Booking Details',  // Updated to match nested route
                'routeParams' => [                // Updated to include both band and booking IDs
                    'band' => $booking->band_id,
                    'booking' => $booking->id
                ],
                'url' => "/bands/{$booking->band_id}/booking/{$booking->id}"  // Updated URL path
            ];

            // Get all band members and owners
            $band = $booking->band;
            $bandMembers = $band->everyone();

            // Notify each member
            foreach ($bandMembers as $member)
            {
                $user = $member->user;
                if ($user)
                {
                    $user->notify(new TTSNotification($notificationData));
                }
            }

            // Also notify the booking author if they're not already in the band
            if ($booking->author && !$bandMembers->contains('user_id', $booking->author_id))
            {
                $booking->author->notify(new TTSNotification($notificationData));
            }
        }
    }
}
