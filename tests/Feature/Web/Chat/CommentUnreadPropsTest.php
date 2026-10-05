<?php

namespace Tests\Feature\Web\Chat;

use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class CommentUnreadPropsTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_event_page_carries_unread_comment_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);
        $event  = $this->makeBookingEvent($band);
        $topic  = app(ConversationService::class)->topicFor($event);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'load-in at 5']);

        $this->actingAs($owner)
            ->get(route('events.show', $event->key))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('unreadCommentCount', 1));
    }

    public function test_event_page_reports_zero_without_a_thread(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $this->actingAs($owner)
            ->get(route('events.show', $event->key))
            ->assertInertia(fn (Assert $page) => $page->where('unreadCommentCount', 0));
    }

    public function test_rehearsal_page_and_wrapping_event_page_agree(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:rehearsals', 'read:events']);
        [$rehearsal, $event] = $this->makeRehearsalEvent($band);
        $topic = app(ConversationService::class)->topicFor($rehearsal);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'new tune']);

        $this->actingAs($owner)
            ->get(route('rehearsals.show', [
                'band' => $band, 'rehearsal_schedule' => $rehearsal->rehearsal_schedule_id, 'rehearsal' => $rehearsal,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('unreadCommentCount', 1));

        $this->actingAs($owner)
            ->get(route('events.show', $event->key))
            ->assertInertia(fn (Assert $page) => $page->where('unreadCommentCount', 1));
    }

    public function test_booking_page_carries_unread_comment_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:bookings']);
        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;
        $topic   = app(ConversationService::class)->topicFor($booking);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'deposit in']);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'contract signed']);

        $this->actingAs($owner)
            ->get(route('Booking Details', ['band' => $band, 'booking' => $booking]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bookings/Show')
                ->where('unreadCommentCount', 2));
    }
}
