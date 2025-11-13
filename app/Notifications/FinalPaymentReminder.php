<?php

namespace App\Notifications;

use App\Models\Bookings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinalPaymentReminder extends Notification implements ShouldQueue
{
    use Queueable;

    protected Bookings $booking;
    protected string $amountDue;
    protected string $eventDate;
    protected int $daysUntilEvent;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bookings $booking)
    {
        $this->booking = $booking;
        $this->amountDue = $booking->amount_due;
        $this->eventDate = $booking->date->format('F j, Y');
        $this->daysUntilEvent = (int) now()->diffInDays($booking->date, false);
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
            ->subject('Final Payment Reminder - ' . $this->booking->name . ' (' . $this->daysUntilEvent . ' days away)')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your event is just **' . $this->daysUntilEvent . ' days away**! This is a reminder about the remaining balance for **' . $this->booking->name . '**.')
            ->line('')
            ->line('**Booking Details:**')
            ->line('Event: ' . $this->booking->name)
            ->line('Date: ' . $this->eventDate)
            ->line('Band: ' . $this->booking->band->name)
            ->line('Location: ' . $this->booking->venue_name)
            ->line('')
            ->line('**Payment Information:**')
            ->line('Remaining Balance: $' . $this->amountDue)
            ->line('Total Booking Price: $' . number_format($this->booking->price, 2))
            ->line('Amount Paid So Far: $' . $this->booking->amount_paid)
            ->line('')
            ->line('To ensure everything is ready for your event, please complete your payment as soon as possible.')
            ->line('')
            ->line('You can make your payment online through our secure customer portal:')
            ->action('Pay Online Now', $loginUrl)
            ->line('')
            ->line('If you have already made this payment or have arranged to pay on the day of the event, please disregard this reminder. Please don\'t be mad at me. I\'m just a bot! Beep Boop')
            ->line('')
            ->line('We look forward to performing at your event! If you have any questions, please contact us.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_name' => $this->booking->name,
            'amount_due' => $this->amountDue,
            'event_date' => $this->eventDate,
            'days_until_event' => $this->daysUntilEvent,
            'type' => 'final_payment_reminder',
        ];
    }
}
