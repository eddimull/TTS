<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells band owners a deposit or balance went overdue. Bell only: owners chase
 * this, so no mail. PushDatabaseNotification also sends it to their devices.
 *
 * @param array $row A row from PaymentDueService.
 */
class PaymentOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $row)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->row['booking_id'],
            'band_id' => $this->row['band_id'],
            'payment_type' => $this->row['type'],
            'text' => $this->text(),
            'route' => 'Booking Finances',
            'routeParams' => ['band' => $this->row['band_id'], 'booking' => $this->row['booking_id']],
            'url' => "/bands/{$this->row['band_id']}/booking/{$this->row['booking_id']}/finances",
        ];
    }

    private function text(): string
    {
        $name = $this->row['booking_name'];
        $amount = '$' . number_format((float) $this->row['amount'], 2);
        $date = Carbon::parse($this->row['overdue_since'])->format('M j');

        return $this->row['type'] === 'deposit'
            ? "Deposit overdue: {$name}, {$amount} was due {$date}"
            : "Balance overdue: {$name}, {$amount} still unpaid after the {$date} show";
    }
}
