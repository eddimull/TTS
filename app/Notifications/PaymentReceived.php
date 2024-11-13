<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Payments;

class PaymentReceived extends Notification
{
    use Queueable;

    public Payments $payment;
    /**
     * Create a new notification instance.
     */
    public function __construct(Payments $payment)
    {
        $this->payment = $payment;
    }


    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Received for Booking')
            ->line('A payment of $' . $this->payment->amount . ' has been received.')
            ->line('Payment Date: ' . $this->payment->formattedPaymentDate)
            ->line('Booking: ' . $this->payment->payable->name)
            ->line('Thank you for your payment!');
    }
}
