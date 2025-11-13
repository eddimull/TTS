<?php

namespace App\Notifications;

use App\Models\Bookings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DepositPaymentReminder extends Notification implements ShouldQueue
{
    use Queueable;

    protected Bookings $booking;
    protected string $depositDue;
    protected string $depositDueDate;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bookings $booking)
    {
        $this->booking = $booking;
        $this->depositDue = $booking->deposit_due;
        $this->depositDueDate = $booking->deposit_due_date
            ? $booking->deposit_due_date->format('F j, Y')
            : 'as soon as possible';
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $loginUrl = route('portal.login');

        return (new MailMessage)
            ->subject('Deposit Payment Reminder - ' . $this->booking->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('This is a friendly reminder that your deposit payment for **' . $this->booking->name . '** was due on **' . $this->depositDueDate . '**.')
            ->line('')
            ->line('**Booking Details:**')
            ->line('Event: ' . $this->booking->name)
            ->line('Date: ' . $this->booking->date->format('F j, Y'))
            ->line('Band: ' . $this->booking->band->name)
            ->line('')
            ->line('**Payment Information:**')
            ->line('Deposit Amount Due: $' . $this->depositDue)
            ->line('Total Booking Price: $' . number_format($this->booking->price, 2))
            ->line('Amount Paid So Far: $' . $this->booking->amount_paid)
            ->line('')
            ->line('You can make your payment online through our secure customer portal:')
            ->action('Pay Online Now', $loginUrl)
            ->line('')
            ->line('If you have already made this payment or have other payment arrangements, please disregard this reminder... and please don\'t be mad at me. I\'m just a bot! Beep Boop')
            ->line('')
            ->line('If you have any questions, please contact us.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_name' => $this->booking->name,
            'deposit_due' => $this->depositDue,
            'deposit_due_date' => $this->depositDueDate,
            'type' => 'deposit_reminder',
        ];
    }
}
