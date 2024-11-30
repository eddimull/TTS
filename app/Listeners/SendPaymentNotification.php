<?php

namespace App\Listeners;

use App\Events\PaymentWasReceived;
use App\Notifications\PaymentReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendPaymentNotification implements ShouldQueue
{
    public function handle(PaymentWasReceived $event)
    {
        $payment = $event->payment;
        $booking = $payment->payable;

        // Notify the customer
        Notification::send($booking->contacts, new PaymentReceived($payment));
    }
}
