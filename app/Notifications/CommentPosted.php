<?php

namespace App\Notifications;

use App\Models\Bookings;
use App\Models\Conversation;
use App\Models\Events;
use App\Models\Message;
use App\Models\Rehearsal;
use Illuminate\Notifications\Notification;

/**
 * Web bell entry for a new comment on an event / rehearsal / booking thread.
 *
 * Database-only on purpose: TTSNotification also mails users who have
 * emailNotifications on, and one email per comment is far too noisy. The
 * data shape matches what Layouts/Authenticated.vue already renders
 * (`text`, `route`, `routeParams`); `comments => 1` makes the target page
 * open its comments drawer.
 */
class CommentPosted extends Notification
{
    public function __construct(
        public readonly Message $message,
        public readonly Conversation $conversation,
        public readonly string $topicTitle,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $route = self::routeFor($this->conversation) ?? ['route' => 'dashboard', 'routeParams' => []];

        $snippet = $this->message->previewSnippet(80);
        $sender  = $this->message->senderDisplayName();

        return [
            'text'            => "{$sender} commented on {$this->topicTitle}: {$snippet}",
            'route'           => $route['route'],
            'routeParams'     => $route['routeParams'],
            'conversation_id' => $this->conversation->id,
            'message_id'      => $this->message->id,
        ];
    }

    /**
     * Web page for a topic conversation's item. Rehearsal-backed events are
     * already canonicalised to the Rehearsal by ConversationService, so an
     * Events conversable here is always a booking/band event page.
     *
     * @return array{route: string, routeParams: array}|null
     */
    public static function routeFor(Conversation $conversation): ?array
    {
        $target = $conversation->conversable;

        return match (true) {
            $target instanceof Events => [
                'route'       => 'events.show',
                'routeParams' => ['key' => $target->key, 'comments' => 1],
            ],
            $target instanceof Bookings => [
                'route'       => 'Booking Details',
                'routeParams' => ['band' => (int) $target->band_id, 'booking' => $target->id, 'comments' => 1],
            ],
            $target instanceof Rehearsal => [
                'route'       => 'rehearsals.show',
                'routeParams' => [
                    'band'               => (int) $target->band_id,
                    'rehearsal_schedule' => (int) $target->rehearsal_schedule_id,
                    'rehearsal'          => $target->id,
                    'comments'           => 1,
                ],
            ],
            default => null,
        };
    }
}
