<?php

namespace Tests\Feature\Web\Chat;

use App\Jobs\SendUserPush;
use App\Notifications\CommentPosted;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class CommentPostedNotificationTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake([SendUserPush::class]); // ProcessChatMessagePush itself runs inline
    }

    public function test_event_comment_notifies_push_audience_minus_author_database_only(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member     = $this->makeMember($band, ['read:events']);
        $noRead     = $this->makeMember($band, []);
        $event      = $this->makeBookingEvent($band);
        $otherEvent = $this->makeBookingEvent($band);
        $entitled   = $this->makeSubAssignedTo($band, $event);
        $unentitled = $this->makeSubAssignedTo($band, $otherEvent);
        $topic      = app(ConversationService::class)->topicFor($event);

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', $topic), ['body' => 'Load-in moved to 4pm, bring the small rig'])
            ->assertCreated();

        foreach ([$member, $entitled] as $recipient) {
            Notification::assertSentTo($recipient, CommentPosted::class, function (CommentPosted $n, array $channels) use ($owner, $event) {
                $data = $n->toArray($owner);

                return $channels === ['database']
                    && $data['route'] === 'events.show'
                    && $data['routeParams'] === ['key' => $event->key, 'comments' => 1]
                    && str_starts_with($data['text'], $owner->name . ' commented on Test Gig: Load-in moved')
                    && $data['conversation_id'] === $n->conversation->id;
            });
        }
        Notification::assertNotSentTo($owner, CommentPosted::class);
        Notification::assertNotSentTo($noRead, CommentPosted::class);
        Notification::assertNotSentTo($unentitled, CommentPosted::class);
    }

    public function test_booking_and_rehearsal_comments_link_to_their_pages(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:bookings', 'read:rehearsals', 'read:events']);
        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;
        [$rehearsal] = $this->makeRehearsalEvent($band);

        $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', app(ConversationService::class)->topicFor($booking)), ['body' => 'paid'])
            ->assertCreated();
        $this->actingAs($owner)
            ->postJson(route('chat.conversations.messages.store', app(ConversationService::class)->topicFor($rehearsal)), ['body' => 'charts'])
            ->assertCreated();

        Notification::assertSentTo($member, CommentPosted::class, fn (CommentPosted $n) =>
            $n->toArray($member)['route'] === 'Booking Details'
            && $n->toArray($member)['routeParams'] === ['band' => $band->id, 'booking' => $booking->id, 'comments' => 1]);

        Notification::assertSentTo($member, CommentPosted::class, fn (CommentPosted $n) =>
            $n->toArray($member)['route'] === 'rehearsals.show'
            && $n->toArray($member)['routeParams'] === [
                'band' => $band->id,
                'rehearsal_schedule' => $rehearsal->rehearsal_schedule_id,
                'rehearsal' => $rehearsal->id,
                'comments' => 1,
            ]);
    }

    public function test_image_only_comment_uses_photo_placeholder_text(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['filesystems.default' => 'local']);
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $topic  = app(ConversationService::class)->topicFor($this->makeBookingEvent($band));

        $this->actingAs($owner)
            ->post(route('chat.conversations.messages.store', $topic), [
                'images' => [\Illuminate\Http\UploadedFile::fake()->image('stage.jpg', 50, 50)],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        Notification::assertSentTo($member, CommentPosted::class, fn (CommentPosted $n) =>
            str_ends_with($n->toArray($member)['text'], ': 📷 Photo'));
    }

    public function test_band_channel_messages_do_not_create_bell_notifications(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $this->makeMember($band);
        $channel = app(ConversationService::class)->bandChannelFor($band);

        $this->actingAs($owner)->postJson(route('chat.conversations.messages.store', $channel), ['body' => 'all'])->assertCreated();

        // Band-channel chatter deliberately stays off the bell — see
        // DirectMessageReceivedTest for DM delivery (added in web-messages T3).
        Notification::assertNothingSent();
    }

    public function test_bell_route_params_resolve_to_a_url_with_comments_query(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $topic = app(ConversationService::class)->topicFor($event);
        $message = $topic->messages()->create(['user_id' => $owner->id, 'body' => 'x']);

        $data = (new CommentPosted($message, $topic, 'Test Gig'))->toArray($owner);
        $url  = route($data['route'], $data['routeParams']);

        $this->assertStringContainsString('/events/' . $event->key, $url);
        $this->assertStringContainsString('comments=1', $url);
    }

    public function test_deleted_item_falls_back_to_dashboard_route(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event   = $this->makeBookingEvent($band);
        $topic   = app(ConversationService::class)->topicFor($event);
        $message = $topic->messages()->create(['user_id' => $owner->id, 'body' => 'x']);

        $event->delete();

        $data = (new CommentPosted($message, $topic->fresh(), 'Thread'))->toArray($owner);

        $this->assertSame('dashboard', $data['route']);
        $this->assertSame([], $data['routeParams']);
    }
}
