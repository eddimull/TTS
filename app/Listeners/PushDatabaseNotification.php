<?php

namespace App\Listeners;

use App\Jobs\SendNotificationPush;
use App\Models\User;
use App\Notifications\CommentPosted;
use App\Notifications\DirectMessageReceived;
use App\Notifications\QuestionnaireSubmitted;
use App\Notifications\RehearsalCancelled;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Push parity for the bell: every database notification a User receives
 * also goes to their devices — except classes whose producer already sends
 * a push of its own (they would arrive twice).
 */
class PushDatabaseNotification
{
    public const SELF_PUSHING = [
        CommentPosted::class,
        DirectMessageReceived::class,
        RehearsalCancelled::class,
        QuestionnaireSubmitted::class,
    ];

    public function handle(NotificationSent $event): void
    {
        if (!config('push.notifications_feed')) {
            return;
        }
        if ($event->channel !== 'database' || !$event->notifiable instanceof User) {
            return;
        }
        foreach (self::SELF_PUSHING as $class) {
            if ($event->notification instanceof $class) {
                return;
            }
        }
        if (!$event->response instanceof DatabaseNotification) {
            return;
        }

        SendNotificationPush::dispatch($event->response->id);
    }
}
