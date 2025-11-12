<?php

namespace App\Notifications;

use App\Models\Payments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BandPaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    protected Payments $payment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Payments $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Determine which channels the notification should use
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database']; // Always send database notification for in-app display

        // Check if user has email notifications enabled
        if (isset($notifiable->emailNotifications) && $notifiable->emailNotifications) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->payment->payable;
        $band = $booking->band;

        // Payment amount is already in dollars due to Price cast
        $amountFormatted = '$' . number_format($this->payment->amount, 2);
        $balanceFormatted = '$' . number_format($booking->amount_due, 2);

        $message = (new MailMessage)
            ->subject('Payment Received - ' . $booking->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A payment has been received for ' . $band->name . '.')
            ->line('**Booking:** ' . $booking->name)
            ->line('**Amount Paid:** ' . $amountFormatted)
            ->line('**Remaining Balance:** ' . $balanceFormatted);

        // Add link to booking if applicable
        if ($booking->id) {
            $message->action('View Booking', route('Booking Details', [
                'band' => $band->id,
                'booking' => $booking->id
            ]));
        }

        return $message->line('Thank you!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->payment->payable;
        $band = $booking->band;

        $amountFormatted = '$' . number_format($this->payment->amount, 2);
        $balanceFormatted = '$' . number_format($booking->amount_due, 2);

        return [
            'text' => "Payment of {$amountFormatted} received for '{$booking->name}'. Remaining balance: {$balanceFormatted}",
            'emailHeader' => 'Payment Received for ' . $band->name,
            'actionText' => 'View Booking',
            'route' => 'Booking Details',
            'routeParams' => [
                'band' => $band->id,
                'booking' => $booking->id,
            ],
            'url' => "/bands/{$band->id}/booking/{$booking->id}",
            'link' => "/bands/{$band->id}/booking/{$booking->id}",
        ];
    }
}
