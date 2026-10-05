<?php

namespace Tests\Feature\Services\Chat;

use App\Services\Chat\ConversationPresenter;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class ConversationPresenterTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_unread_count_is_zero_when_no_thread_exists_yet(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $this->assertSame(0, app(ConversationPresenter::class)->unreadCountFor($owner, $event));
        // Probing must NOT create the conversation.
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_unread_count_counts_other_users_messages(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $event  = $this->makeBookingEvent($band);
        $topic  = app(ConversationService::class)->topicFor($event);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'one']);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'two']);
        $topic->messages()->create(['user_id' => $owner->id,  'body' => 'mine']);

        $this->assertSame(2, app(ConversationPresenter::class)->unreadCountFor($owner, $event));
    }

    public function test_unread_count_canonicalises_rehearsal_backed_events(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:rehearsals', 'read:events']);
        [$rehearsal, $event] = $this->makeRehearsalEvent($band);
        $topic = app(ConversationService::class)->topicFor($rehearsal);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'bring charts']);

        $presenter = app(ConversationPresenter::class);
        $this->assertSame(1, $presenter->unreadCountFor($owner, $event));
        $this->assertSame(1, $presenter->unreadCountFor($owner, $rehearsal));
    }

    public function test_unread_count_is_null_when_user_cannot_view_the_topic(): void
    {
        [, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $sub   = $this->makeSubAssignedTo($band, $event);

        // Subs never see booking threads.
        $this->assertNull(app(ConversationPresenter::class)->unreadCountFor($sub, $event->eventable));
        // …but do see the event thread they are entitled to.
        $this->assertSame(0, app(ConversationPresenter::class)->unreadCountFor($sub, $event));
    }

    public function test_thread_page_shape_matches_mobile_contract(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $topic = app(ConversationService::class)->topicFor($event);
        $topic->messages()->create(['user_id' => $owner->id, 'body' => 'hello']);

        $page = app(ConversationPresenter::class)->threadPage($owner, $topic);

        $this->assertSame(['conversation', 'messages', 'participants', 'channel', 'has_more'], array_keys($page));
        $this->assertSame('private-conversation.' . $topic->id, $page['channel']);
        $this->assertSame('hello', $page['messages'][0]['body']);
        $this->assertSame('event', $page['conversation']['topic_type']);
        $this->assertSame('Test Gig', $page['conversation']['title']);
    }
}
