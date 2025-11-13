<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ContactPortalAccessGranted extends Notification implements ShouldQueue
{
    use Queueable;

    protected $temporaryPassword;
    protected $bookingName;
    protected $bandName;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $temporaryPassword, string $bookingName, string $bandName)
    {
        $this->temporaryPassword = $temporaryPassword;
        $this->bookingName = $bookingName;
        $this->bandName = $bandName;
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
            ->subject('Portal Access - ' . $this->bandName)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You have been granted access to the client portal for **' . $this->bandName . '**.')
            ->line('You can now log in to view your booking details and make payments online.')
            ->line('')
            ->line('**Booking:** ' . $this->bookingName)
            ->line('')
            ->line('**Your Login Credentials:**')
            ->line('Email: ' . $notifiable->email)
            ->line('Temporary Password: `' . $this->temporaryPassword . '`')
            ->line('')
            ->action('Log In to Portal', $loginUrl)
            ->line('For security, please change your password after your first login.')
            ->line('If you have any questions, please contact us.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'temporary_password' => $this->temporaryPassword,
            'booking_name' => $this->bookingName,
            'band_name' => $this->bandName,
        ];
    }
}
