<?php

namespace Tests\Feature\Web\Chat;

use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

/**
 * Every booking tab sends `unreadCommentCount`, so the Comments button in
 * BookingLayout is available on all of them, not only the overview.
 */
class BookingTabsUnreadPropTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_every_booking_tab_carries_the_unread_comment_count(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member  = $this->makeMember($band, ['read:bookings']);
        $event   = $this->makeBookingEvent($band);
        $booking = $event->eventable;
        // The Contract tab redirects to Contacts when a booking that wants a
        // contract has no contacts; give it one so every tab renders.
        $contact = \App\Models\Contacts::factory()->create(['band_id' => $band->id]);
        $booking->contacts()->attach($contact->id, ['role' => 'primary']);
        $topic   = app(ConversationService::class)->topicFor($booking);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'deposit in']);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'contract signed']);

        // Every GET tab under bands/{band}/booking/{booking}, including the overview.
        foreach (['', 'contacts', 'events', 'media', 'finances', 'contract', 'lineup', 'payout', 'history'] as $segment) {
            $response = $this->actingAs($owner)
                ->get(rtrim("/bands/{$band->id}/booking/{$booking->id}/{$segment}", '/'));

            $this->assertSame(200, $response->status(), "tab '{$segment}' did not render");
            $response->assertInertia(fn (Assert $page) => $page->where('unreadCommentCount', 2));
        }
    }
}
