<?php

namespace App\Notifications;

use App\Models\Rehearsal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class RehearsalCancelled extends Notification
{
    use Queueable;

    public function __construct(
        public Rehearsal $rehearsal,
        public bool $isCancelled,
        public ?string $date,
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->emailNotifications) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function headline(): string
    {
        $name = $this->rehearsal->rehearsalSchedule?->name ?? 'Rehearsal';
        $when = '';
        if ($this->date) {
            try {
                $when = ' on ' . Carbon::parse($this->date)->format('F j, Y');
            } catch (\Throwable) {
                $when = '';
            }
        }

        return $this->isCancelled
            ? "{$name}{$when} was cancelled"
            : "{$name}{$when} is back on";
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->headline())
            ->line($this->headline())
            ->action('View Rehearsals', config('app.url') . '/rehearsal-schedules');
    }

    public function toArray($notifiable): array
    {
        return [
            'text'         => $this->headline(),
            'link'         => '/rehearsal-schedules',
            'rehearsal_id' => $this->rehearsal->id,
            'is_cancelled' => $this->isCancelled,
            'date'         => $this->date,
        ];
    }
}
