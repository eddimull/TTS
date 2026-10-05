<?php

namespace Tests\Feature\Services\Chat;

use App\Services\Chat\ConversationPresenter;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class ConversationPresenterListTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_list_contains_lazily_created_band_channel_and_dms_sorted_by_last_message(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band);
        $service = app(ConversationService::class);

        $dm = $service->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'hey there']);

        $rows = app(ConversationPresenter::class)->listFor($owner);

        $this->assertCount(2, $rows);
        $this->assertSame('dm', $rows[0]['type'], 'conversation with the newest message sorts first');
        $this->assertSame('band', $rows[1]['type']);
        $this->assertSame($band->name, $rows[1]['title']);
        $this->assertSame(1, $rows[0]['unread_count']);
        $this->assertSame(
            ['id', 'type', 'band_id', 'title', 'topic_type', 'last_message_preview', 'last_message_at', 'unread_count', 'can_moderate'],
            array_keys($rows[0]),
        );
    }

    public function test_topic_threads_are_listed_only_when_they_have_messages_and_are_visible(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:events', 'read:bookings']);
        $service = app(ConversationService::class);

        $eventWithMsg = $this->makeBookingEvent($band);
        $eventEmpty   = $this->makeBookingEvent($band);
        $service->topicFor($eventWithMsg)->messages()->create(['user_id' => $owner->id, 'body' => 'load-in 5']);
        $service->topicFor($eventEmpty); // created, never posted in

        $booking = $eventWithMsg->eventable;
        $service->topicFor($booking)->messages()->create(['user_id' => $owner->id, 'body' => 'deposit paid']);

        $sub = $this->makeSubAssignedTo($band, $eventWithMsg);

        $memberRows = app(ConversationPresenter::class)->listFor($member);
        $this->assertCount(3, $memberRows, 'band channel + event thread + booking thread');
        $this->assertEqualsCanonicalizing(['band', 'topic', 'topic'], $memberRows->pluck('type')->all());

        $subRows = app(ConversationPresenter::class)->listFor($sub);
        $this->assertCount(1, $subRows, 'subs: no band channel, no booking thread, only the entitled event thread');
        $this->assertSame('event', $subRows[0]['topic_type']);
    }

    public function test_unread_total_sums_every_row(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:events']);
        $service = app(ConversationService::class);

        $dm = $service->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'a']);
        $dm->messages()->create(['user_id' => $member->id, 'body' => 'b']);
        $service->bandChannelFor($band)->messages()->create(['user_id' => $member->id, 'body' => 'c']);
        $service->topicFor($this->makeBookingEvent($band))->messages()->create(['user_id' => $member->id, 'body' => 'd']);

        $this->assertSame(4, app(ConversationPresenter::class)->unreadTotalFor($owner));
        $this->assertSame(0, app(ConversationPresenter::class)->unreadTotalFor($member));
    }
}
