<?php

namespace App\Notifications;

use App\Models\Events;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MediaUploadedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The event for which media was uploaded.
     */
    public Events $event;

    /**
     * The number of media files in the event folder.
     */
    public int $mediaCount;

    /**
     * Create a new notification instance.
     */
    public function __construct(Events $event, int $mediaCount)
    {
        $this->event = $event;
        $this->mediaCount = $mediaCount;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $eventName = $this->event->title;
        $fileWord = $this->mediaCount === 1 ? 'file' : 'files';
        $bandName = $this->event->eventable->band->name ?? 'your band';

        return (new MailMessage)
            ->subject("New Media Available for {$eventName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("New photos and videos have been uploaded for your event: **{$eventName}**")
            ->line("There " . ($this->mediaCount === 1 ? 'is' : 'are') . " now **{$this->mediaCount} {$fileWord}** available in your event gallery.")
            ->action('View Event Media', route('portal.media'))
            ->line("Thank you for choosing {$bandName}!");
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'event_date' => $this->event->date->format('Y-m-d'),
            'media_count' => $this->mediaCount,
            'media_folder_path' => $this->event->media_folder_path,
            'message' => "New media uploaded for {$this->event->title}",
        ];
    }
}
