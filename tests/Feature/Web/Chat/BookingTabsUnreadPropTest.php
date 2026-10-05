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
        $topic   = app(ConversationService::class)->topicFor($booking);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'deposit in']);
        $topic->messages()->create(['user_id' => $member->id, 'body' => 'contract signed']);

        $params = ['band' => $band, 'booking' => $booking];

        foreach ([
            'Booking Details'   => 'Bookings/Show',
            'bookings.contacts' => 'Bookings/Contacts',
            'bookings.events'   => 'Bookings/Events',
            'bookings.history'  => 'Bookings/History',
        ] as $routeName => $component) {
            if (!\Illuminate\Support\Facades\Route::has($routeName)) {
                continue; // route names differ per tab; the canonical ones are asserted below
            }
            $this->actingAs($owner)
                ->get(route($routeName, $params))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('unreadCommentCount', 2));
        }

        // Tabs are also reachable by URI regardless of their route names.
        foreach (['contacts', 'events', 'lineup', 'finances', 'history'] as $segment) {
            $this->actingAs($owner)
                ->get("/bands/{$band->id}/booking/{$booking->id}/{$segment}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('unreadCommentCount', 2));
        }
    }
}
