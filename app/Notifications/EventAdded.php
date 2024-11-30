<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventAdded extends Notification
{
    use Queueable;
    protected $data;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $notificationMethods = ['database'];
        if ($notifiable->emailNotifications)
        {
            array_push($notificationMethods, 'mail');
        }
        return $notificationMethods;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line('There was an update to an event')
            ->action('Check out the event', config('app.url') .  $this->data['url'])
            ->line($this->data['text']);
    }


    public function toDatabase($notifiable)
    {
        return $this->data;
    }
}
