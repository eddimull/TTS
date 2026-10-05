<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Jobs\SendNotificationPush;
use App\Jobs\SendUserPush;
use App\Models\Bandnotification;
use App\Notifications\CommentPosted;
use App\Notifications\TTSNotification;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class NotificationPushTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_flag_off_by_default_means_no_feed_push(): void
    {
        Queue::fake([SendNotificationPush::class, SendUserPush::class]);
        [$owner] = $this->makeOwnerWithBand();

        $owner->notify(new TTSNotification(['text' => 'status changed', 'route' => 'dashboard', 'routeParams' => null]));

        $this->assertFalse(config('push.notifications_feed'));
        Queue::assertNotPushed(SendNotificationPush::class);
    }

    public function test_flag_on_pushes_bell_only_notifications_with_type_notification_and_deeplink(): void
    {
        config(['push.notifications_feed' => true]);
        Queue::fake([SendUserPush::class]); // SendNotificationPush runs inline
        [$owner, $band] = $this->makeOwnerWithBand();
        $booking = $this->makeBookingEvent($band)->eventable;

        $owner->notify(new TTSNotification([
            'text' => "Booking '{$booking->name}' status changed from pending to confirmed",
            'route' => 'Booking Details', 'routeParams' => ['band' => $band->id, 'booking' => $booking->id],
        ]));
        $row = Bandnotification::where('notifiable_id', $owner->id)->first();

        Queue::assertPushed(SendUserPush::class, 1);
        Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) =>
            $job->userId === $owner->id
            && $job->alert === true
            && $job->dedupeKey === 'notification:' . $row->id
            && $job->data['type'] === 'notification'
            && $job->data['notificationId'] === $row->id
            && $job->data['kind'] === 'booking'
            && $job->data['deeplink'] === "/bookings/{$band->id}/{$booking->id}"
            && str_contains($job->data['body'], 'status changed')
            && is_string($job->data['title']) && $job->data['title'] !== '');
    }

    public function test_self_pushing_notification_classes_are_not_doubled(): void
    {
        config(['push.notifications_feed' => true]);
        Queue::fake([SendUserPush::class, SendNotificationPush::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $topic  = app(ConversationService::class)->topicFor($this->makeBookingEvent($band));

        // A comment: ProcessChatMessagePush sends its own chat_message push AND stores CommentPosted.
        $this->actingAs($owner)
            ->postJson("/api/mobile/conversations/{$topic->id}/messages", ['body' => 'hey'])
            ->assertCreated();

        Queue::assertNotPushed(SendNotificationPush::class);
        Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) => $job->data['type'] === 'chat_message');
        Queue::assertNotPushed(SendUserPush::class, fn (SendUserPush $job) => $job->data['type'] === 'notification');
    }

    public function test_subclasses_of_self_pushing_notifications_are_not_pushed(): void
    {
        config(['push.notifications_feed' => true]);
        Queue::fake([SendNotificationPush::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $event  = $this->makeBookingEvent($band);
        $topic  = app(ConversationService::class)->topicFor($event);
        $message = $topic->messages()->create(['user_id' => $owner->id, 'body' => 'hey']);

        $member->notify(new class ($message, $topic, 'Test Gig') extends CommentPosted {
        });

        Queue::assertNotPushed(SendNotificationPush::class);
    }

    public function test_contact_notifications_never_push(): void
    {
        config(['push.notifications_feed' => true]);
        Queue::fake([SendNotificationPush::class]);
        [$owner, $band] = $this->makeOwnerWithBand();
        $contact = \App\Models\Contacts::factory()->create(['band_id' => $band->id]);

        $contact->notify(new TTSNotification(['text' => 'portal', 'route' => 'dashboard', 'routeParams' => null]));

        Queue::assertNotPushed(SendNotificationPush::class);
    }
}
