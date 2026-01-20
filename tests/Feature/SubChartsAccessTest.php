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
use App\Services\UserEventsService;
use Illuminate\Support\Facades\Auth;

class SubChartsAccessTest extends TestCase
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

    public function test_sub_only_sees_charts_for_invited_events()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Create events with charts
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 1, 'title' => 'Song A', 'composer' => 'Composer 1'],
                        ['id' => 2, 'title' => 'Song B', 'composer' => 'Composer 2'],
                    ]
                ]
            ]
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 3, 'title' => 'Song C', 'composer' => 'Composer 3'],
                    ]
                ]
            ]
        ]);

        // Only invite sub to event 1
        EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => false,
        ]);

        Auth::login($sub);
        $charts = $this->userEventsService->getUpcomingCharts();

        // Should only see charts from event 1
        $this->assertCount(2, $charts);
        $chartTitles = $charts->pluck('title')->toArray();
        $this->assertContains('Song A', $chartTitles);
        $this->assertContains('Song B', $chartTitles);
        $this->assertNotContains('Song C', $chartTitles);
    }

    public function test_sub_without_events_sees_no_charts()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Create event with charts but don't invite the sub
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 1, 'title' => 'Song A', 'composer' => 'Composer 1'],
                    ]
                ]
            ]
        ]);

        Auth::login($sub);
        $charts = $this->userEventsService->getUpcomingCharts();

        // Sub should see no charts
        $this->assertCount(0, $charts);
    }

    public function test_sub_sees_charts_from_multiple_bands()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Create second band
        $band2 = Bands::factory()->create(['name' => 'Second Band']);

        // Create events in both bands with charts
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 1, 'title' => 'Band 1 Song', 'composer' => 'Composer 1'],
                    ]
                ]
            ]
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $band2->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 2, 'title' => 'Band 2 Song', 'composer' => 'Composer 2'],
                    ]
                ]
            ]
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
        $charts = $this->userEventsService->getUpcomingCharts();

        // Should see charts from both bands
        $this->assertCount(2, $charts);
        $chartTitles = $charts->pluck('title')->toArray();
        $this->assertContains('Band 1 Song', $chartTitles);
        $this->assertContains('Band 2 Song', $chartTitles);
    }

    public function test_sub_does_not_see_charts_from_pending_invitations()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 1, 'title' => 'Accepted Event Song', 'composer' => 'Composer 1'],
                    ]
                ]
            ]
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 2, 'title' => 'Pending Event Song', 'composer' => 'Composer 2'],
                    ]
                ]
            ]
        ]);

        // Create one accepted and one pending invitation
        EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => false,
        ]);

        EventSubs::create([
            'event_id' => $event2->id,
            'band_id' => $this->band->id,
            'user_id' => $sub->id,
            'email' => $sub->email,
            'pending' => true, // Still pending
        ]);

        Auth::login($sub);
        $charts = $this->userEventsService->getUpcomingCharts();

        // Should only see charts from accepted invitation
        $this->assertCount(1, $charts);
        $this->assertEquals('Accepted Event Song', $charts->first()['title']);
    }

    public function test_sub_has_permission_to_view_charts()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Verify sub has the view-charts permission
        $this->assertTrue($sub->hasPermissionTo('view-charts'));
        $this->assertTrue($sub->hasPermissionTo('download-charts'));
    }

    public function test_sub_has_permission_to_view_event_details()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Verify sub has event-related permissions
        $this->assertTrue($sub->hasPermissionTo('view-event-details'));
        $this->assertTrue($sub->hasPermissionTo('view-event-notes'));
    }

    public function test_sub_has_permission_to_view_roster()
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        // Verify sub can view roster (but not financials except own payout)
        $this->assertTrue($sub->hasPermissionTo('view-roster'));
        $this->assertTrue($sub->hasPermissionTo('view-own-payout'));
    }

    public function test_band_member_sees_all_charts_not_just_sub_charts()
    {
        $memberSub = User::factory()->create();
        $memberSub->assignRole('sub');

        // Make them a band owner
        BandOwners::create([
            'user_id' => $memberSub->id,
            'band_id' => $this->band->id
        ]);

        // Create two events with charts
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(5),
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 1, 'title' => 'Song A', 'composer' => 'Composer 1'],
                    ]
                ]
            ]
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(10),
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 2, 'title' => 'Song B', 'composer' => 'Composer 2'],
                    ]
                ]
            ]
        ]);

        // Only invite them as sub to event1
        EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $memberSub->id,
            'email' => $memberSub->email,
            'pending' => false,
        ]);

        Auth::login($memberSub);
        $charts = $this->userEventsService->getUpcomingCharts();

        // Should see ALL band charts, not just sub event charts
        $this->assertCount(2, $charts);
    }
}
