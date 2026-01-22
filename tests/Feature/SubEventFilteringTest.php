<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Bands;
use App\Models\User;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\EventSubs;
use App\Models\BandOwners;
use App\Models\BandMembers;
use App\Services\UserEventsService;
use Illuminate\Support\Facades\Auth;

class SubEventFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected $userEventsService;
    protected $band;
    protected $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sub role and permissions
        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);

        $this->userEventsService = new UserEventsService();

        // Create test band and owner
        $this->band = Bands::factory()->create(['name' => 'Test Band']);
        $this->owner = User::factory()->create();

        BandOwners::create([
            'user_id' => $this->owner->id,
            'band_id' => $this->band->id
        ]);
    }

    public function test_sub_only_sees_invited_events()
    {
        // Create a user who is ONLY a sub (not a band member/owner)
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Create multiple events for the band
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
            'title' => 'Event 1 - Invited',
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
            'title' => 'Event 2 - NOT Invited',
        ]);

        $booking3 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event3 = Events::factory()->create([
            'eventable_id' => $booking3->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(15),
            'title' => 'Event 3 - Invited',
        ]);

        // Invite sub to event 1 and 3 only
        EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => false,
            'accepted_at' => now(),
        ]);

        EventSubs::create([
            'event_id' => $event3->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => false,
            'accepted_at' => now(),
        ]);

        // Act as the sub and get their events
        Auth::login($sub);
        $events = $this->userEventsService->getEvents();

        // Assert: Sub should only see events 1 and 3
        $this->assertCount(2, $events);
        $eventIds = $events->pluck('id')->toArray();
        $this->assertContains($event1->id, $eventIds);
        $this->assertContains($event3->id, $eventIds);
        $this->assertNotContains($event2->id, $eventIds);
    }

    public function test_sub_does_not_see_pending_invitations_in_event_list()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
        ]);

        // Create one accepted and one pending invitation
        EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => false,
            'accepted_at' => now(),
        ]);

        EventSubs::create([
            'event_id' => $event2->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => true, // Still pending
        ]);

        Auth::login($sub);
        $events = $this->userEventsService->getEvents();

        // Should only see accepted event
        $this->assertCount(1, $events);
        $this->assertEquals($event1->id, $events->first()->id);
    }

    public function test_band_member_sees_all_events_not_just_sub_events()
    {
        // Create a user who is both a band member AND a sub
        $memberSub = User::factory()->create();
        $memberSub->assignRole('sub');

        BandMembers::create([
            'user_id' => $memberSub->id,
            'band_id' => $this->band->id
        ]);

        // Create two events
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
        ]);

        // Only invite to event1 as a sub
        EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $memberSub->id,
            'email' => $memberSub->email,
            'pending' => false,
        ]);

        Auth::login($memberSub);
        $events = $this->userEventsService->getEvents();

        // Should see ALL band events, not just sub events
        $this->assertCount(2, $events);
    }

    public function test_band_owner_sees_all_events()
    {
        // Owners should see all events regardless of sub invitations
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
        ]);

        Auth::login($this->owner);
        $events = $this->userEventsService->getEvents();

        // Owner should see all band events
        $this->assertCount(2, $events);
    }

    public function test_sub_without_invitations_sees_no_events()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Create events but don't invite the sub
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
        ]);

        Auth::login($sub);
        $events = $this->userEventsService->getEvents();

        // Sub with no invitations should see zero events
        $this->assertCount(0, $events);
    }

    public function test_sub_sees_events_from_multiple_bands()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Create second band
        $band2 = Bands::factory()->create(['name' => 'Second Band']);

        // Create events in both bands
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $band2->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
        ]);

        // Invite sub to both bands' events
        EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => false,
        ]);

        EventSubs::create([
            'event_id' => $event2->id,
            'band_id' => $band2->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => false,
        ]);

        Auth::login($sub);
        $events = $this->userEventsService->getEvents();

        // Sub should see events from both bands
        $this->assertCount(2, $events);
    }

    public function test_sub_event_filtering_respects_date_range()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Create events at different dates
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $pastEvent = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->subDays(5),
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $futureEvent = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
        ]);

        // Invite sub to both events
        EventSubs::create([
            'event_id' => $pastEvent->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => false,
        ]);

        EventSubs::create([
            'event_id' => $futureEvent->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => false,
        ]);

        Auth::login($sub);

        // Get only future events (default behavior is last 72 hours)
        $events = $this->userEventsService->getEvents(now()->subHours(1));

        // Should only see future event
        $this->assertCount(1, $events);
        $this->assertEquals($futureEvent->id, $events->first()->id);
    }
}
