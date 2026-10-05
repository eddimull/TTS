<?php

namespace App\Jobs;

use App\Models\Bandnotification;
use App\Models\User;
use App\Services\Notifications\NotificationPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Push one stored bell notification to its user's devices, carrying the
 * presenter's deeplink so a tap lands where the in-app feed would go.
 */
class SendNotificationPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $notificationId) {}

    public function handle(NotificationPresenter $presenter): void
    {
        $row = Bandnotification::find($this->notificationId);
        if (!$row || !$row->notifiable instanceof User) {
            return;
        }

        $user      = $row->notifiable;
        $presented = $presenter->present($row, $user);

        SendUserPush::dispatch($user->id, [
            'type'           => 'notification',
            'notificationId' => (string) $row->id,
            'kind'           => $presented['kind'],
            'title'          => config('app.name', 'TTS Band'),
            'body'           => $presented['text'],
            'deeplink'       => $presented['deeplink'],
        ], 'notification:' . $row->id, true);
    }
}
