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
use App\Models\BandSubs;
use App\Models\BandMembers;
use App\Services\UserEventsService;

class SubEventAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $band;
    protected $owner;
    protected $subUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sub role and permissions
        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);

        // Create test data
        $this->band = Bands::factory()->create(['name' => 'Test Band']);
        $this->owner = User::factory()->create();

        BandOwners::create([
            'user_id' => $this->owner->id,
            'band_id' => $this->band->id
        ]);

        // Create a sub user with sub role
        $this->subUser = User::factory()->create([
            'name' => 'Kazoo Master',
            'email' => 'kazoo@example.com'
        ]);
        $this->subUser->assignRole('sub');

        // Add to band_subs (NOT band_members)
        BandSubs::create([
            'user_id' => $this->subUser->id,
            'band_id' => $this->band->id,
        ]);
    }

    public function test_sub_user_only_sees_invited_events()
    {
        // Create 3 events
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
            'title' => 'Event 1 - Invited'
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(14),
            'title' => 'Event 2 - Not Invited'
        ]);

        $booking3 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event3 = Events::factory()->create([
            'eventable_id' => $booking3->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(21),
            'title' => 'Event 3 - Invited'
        ]);

        // Invite sub to events 1 and 3 only
        $eventSub1 = EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $this->subUser->id,
            'email' => $this->subUser->email,
            'pending' => false,
            'accepted_at' => now(),
        ]);

        $eventSub3 = EventSubs::create([
            'event_id' => $event3->id,
            'band_id' => $this->band->id,
            'user_id' => $this->subUser->id,
            'email' => $this->subUser->email,
            'pending' => false,
            'accepted_at' => now(),
        ]);

        // Login as sub user
        $this->actingAs($this->subUser);

        // Get events using UserEventsService
        $userEventsService = new UserEventsService();
        $events = $userEventsService->getEvents();

        // Sub should only see 2 events (events 1 and 3)
        $this->assertCount(2, $events, 'Sub should only see 2 invited events');

        $eventTitles = $events->pluck('title')->toArray();
        $this->assertContains('Event 1 - Invited', $eventTitles);
        $this->assertContains('Event 3 - Invited', $eventTitles);
        $this->assertNotContains('Event 2 - Not Invited', $eventTitles);
    }

    public function test_sub_user_is_not_in_band_members_table()
    {
        // Verify sub user is in band_subs but NOT in band_members
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $this->subUser->id,
            'band_id' => $this->band->id,
        ]);

        $this->assertDatabaseMissing('band_members', [
            'user_id' => $this->subUser->id,
            'band_id' => $this->band->id,
        ]);
    }

    public function test_sub_user_has_correct_role_detection()
    {
        $this->actingAs($this->subUser);

        // Check role detection
        $this->assertTrue($this->subUser->hasRole('sub'));

        // Check band access
        $ownedBands = $this->subUser->bandOwner()->pluck('bands.id')->toArray();
        $memberBands = $this->subUser->bandMember()->pluck('bands.id')->toArray();

        $this->assertEmpty($ownedBands, 'Sub should not own any bands');
        $this->assertEmpty($memberBands, 'Sub should not be a member of any bands');
    }

    public function test_dashboard_only_shows_sub_events()
    {
        // Create 2 events, invite sub to only 1
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
            'title' => 'Event 1 - Invited'
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(14),
            'title' => 'Event 2 - Not Invited'
        ]);

        // Invite sub to event 1 only
        EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $this->subUser->id,
            'email' => $this->subUser->email,
            'pending' => false,
            'accepted_at' => now(),
        ]);

        // Visit dashboard as sub user
        $response = $this->actingAs($this->subUser)->get('/dashboard');

        $response->assertStatus(200);

        // Check that only invited event is in the events prop
        $response->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.title', 'Event 1 - Invited')
        );
    }
}
