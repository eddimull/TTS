<?php

namespace App\Notifications;

use App\Models\Bookings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a booking contact their balance is settled after the band wrote off
 * what was left. Deliberately silent on the amount and on how it was settled.
 */
class BalanceSettled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Bookings $booking)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your balance is settled')
            ->view('email.balance-settled', [
                'performance' => $this->booking->name,
                'bandName' => $this->booking->band?->name,
            ]);
    }
}
