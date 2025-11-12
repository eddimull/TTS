<?php

namespace App\Listeners;

use App\Events\PaymentWasReceived;
use App\Notifications\PaymentReceived;
use App\Notifications\BandPaymentReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendPaymentNotification implements ShouldQueue
{
    public function handle(PaymentWasReceived $event)
    {
        $payment = $event->payment;
        $booking = $payment->payable;
        $band = $booking->band;

        // Notify the customer (contacts)
        Notification::send($booking->contacts, new PaymentReceived($payment));

        // Notify band owners and members
        $bandUsers = collect();

        // Get all band owners
        if ($band->owners) {
            foreach ($band->owners as $owner) {
                if ($owner->user) {
                    $bandUsers->push($owner->user);
                }
            }
        }

        // Get all band members
        if ($band->members) {
            foreach ($band->members as $member) {
                if ($member->user) {
                    $bandUsers->push($member->user);
                }
            }
        }

        // Remove duplicates and send notifications
        $bandUsers = $bandUsers->unique('id');

        if ($bandUsers->isNotEmpty()) {
            Notification::send($bandUsers, new BandPaymentReceived($payment));
        }
    }
}
