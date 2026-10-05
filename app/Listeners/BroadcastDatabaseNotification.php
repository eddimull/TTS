<?php

namespace App\Listeners;

use App\Events\NotificationChanged;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/** Every database notification stored for a User announces itself to that user's channel. */
class BroadcastDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || !$event->notifiable instanceof User) {
            return;
        }

        $id = $event->response instanceof DatabaseNotification ? $event->response->id : null;

        NotificationChanged::dispatch($event->notifiable->id, $id, 'created');
    }
}
