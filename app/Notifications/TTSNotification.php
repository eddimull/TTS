<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use function PHPUnit\Framework\throwException;

class TTSNotification extends Notification
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


        if (!isset($this->data['text']))
        {
            $this->data['text'] = '';
        }
        if (!isset($this->data['link']))
        {
            $this->data['link'] = '/';
        }
        if (!isset($this->data['route']))
        {
            $this->data['route'] = 'dashboard';
        }
        if (!isset($this->data['routeParams']))
        {
            $this->data['routeParams'] = '';
        }
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
            ->line(isset($this->data['emailHeader']) ? $this->data['emailHeader'] : '')
            ->action(isset($this->data['actionText']) ? $this->data['actionText'] : 'Check it out', config('app.url') .  (isset($this->data['url']) ? $this->data['url'] : ''))
            ->line(isset($this->data['text']) ? $this->data['text'] : '');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }
}
