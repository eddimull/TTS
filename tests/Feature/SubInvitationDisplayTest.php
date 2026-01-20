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
use App\Models\BandRole;

class SubInvitationDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected $band;
    protected $owner;
    protected $event;
    protected $bandRole;

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

        // Create a band role (instrument)
        $this->bandRole = BandRole::create([
            'band_id' => $this->band->id,
            'name' => 'Alto Saxophone',
            'display_order' => 1,
            'is_active' => true,
        ]);

        // Create a booking and event with charts/songs
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
            'title' => 'Jazz Night at Blue Note',
            'time' => '20:00:00',
            'additional_data' => [
                'performance' => [
                    'charts' => [
                        ['id' => 1, 'title' => 'Take Five', 'composer' => 'Dave Brubeck'],
                        ['id' => 2, 'title' => 'So What', 'composer' => 'Miles Davis'],
                        ['id' => 3, 'title' => 'All Blues', 'composer' => 'Miles Davis'],
                    ],
                    'songs' => [
                        ['title' => 'Autumn Leaves', 'url' => 'https://example.com/autumn-leaves'],
                        ['title' => 'Stella by Starlight', 'url' => null],
                    ],
                ],
                'times' => [
                    ['title' => 'Load In', 'time' => now()->addDays(7)->setTime(19, 0)->format('Y-m-d H:i')],
                    ['title' => 'Start', 'time' => now()->addDays(7)->setTime(20, 0)->format('Y-m-d H:i')],
                    ['title' => 'End', 'time' => now()->addDays(7)->setTime(23, 0)->format('Y-m-d H:i')],
                ],
            ],
        ]);
    }

    public function test_sub_invitation_displays_all_critical_information()
    {
        // Create a comprehensive event sub invitation with all fields
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'band_role_id' => $this->bandRole->id,
            'email' => 'sub@example.com',
            'name' => 'John Doe',
            'phone' => '555-1234',
            'payout_amount' => 15000, // $150.00
            'notes' => 'Load in at 7pm. Bring your own mouthpiece.',
            'pending' => true,
        ]);

        $response = $this->get("/sub/invitation/{$eventSub->invitation_key}");

        $response->assertStatus(200);

        // Check all critical information is passed to the view
        $response->assertInertia(fn ($page) => $page
            ->component('SubInvitation/Show')

            // 1. INSTRUMENT/ROLE - Critical for subs to know what they're playing
            ->where('roleName', 'Alto Saxophone')
            ->where('eventSub.band_role_id', $this->bandRole->id)

            // 2. PAY - Critical financial information
            ->where('eventSub.payout_amount', 15000)

            // 3. MUSIC SELECTION - What they need to prepare
            ->where('charts', function ($charts) {
                return count($charts) === 3
                    && $charts[0]['title'] === 'Take Five'
                    && $charts[1]['title'] === 'So What'
                    && $charts[2]['title'] === 'All Blues';
            })
            ->where('songs', function ($songs) {
                return count($songs) === 2
                    && $songs[0]['title'] === 'Autumn Leaves'
                    && $songs[1]['title'] === 'Stella by Starlight';
            })

            // 4. DURATION - Start and end times
            ->where('event.start_time', '20:00:00')
            ->where('event.end_time', '23:00:00')

            // Additional important information
            ->where('event.title', 'Jazz Night at Blue Note')
            ->where('eventSub.notes', 'Load in at 7pm. Bring your own mouthpiece.')
            ->where('band.name', 'Test Band')
        );
    }

    public function test_invitation_displays_payout_amount_correctly()
    {
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'sub@example.com',
            'payout_amount' => 25000, // $250.00
        ]);

        $response = $this->get("/sub/invitation/{$eventSub->invitation_key}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('eventSub.payout_amount', 25000)
            );
    }

    public function test_invitation_displays_instrument_role()
    {
        $trumpetRole = BandRole::firstOrCreate(
            [
                'band_id' => $this->band->id,
                'name' => 'Trumpet',
            ],
            [
                'display_order' => 2,
                'is_active' => true,
            ]
        );

        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'band_role_id' => $trumpetRole->id,
            'email' => 'trumpeter@example.com',
        ]);

        $response = $this->get("/sub/invitation/{$eventSub->invitation_key}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('roleName', 'Trumpet')
            );
    }

    public function test_invitation_displays_charts_list()
    {
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'sub@example.com',
        ]);

        $response = $this->get("/sub/invitation/{$eventSub->invitation_key}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('charts', function ($charts) {
                    // Verify all 3 charts are present with correct data
                    if (count($charts) !== 3) return false;

                    $chartsArray = is_array($charts) ? $charts : collect($charts)->toArray();
                    $chartTitles = array_map(fn($c) => $c['title'], $chartsArray);
                    return in_array('Take Five', $chartTitles)
                        && in_array('So What', $chartTitles)
                        && in_array('All Blues', $chartTitles);
                })
            );
    }

    public function test_invitation_displays_songs_list()
    {
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'sub@example.com',
        ]);

        $response = $this->get("/sub/invitation/{$eventSub->invitation_key}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('songs', function ($songs) {
                    // Verify songs are present
                    if (count($songs) !== 2) return false;

                    return $songs[0]['title'] === 'Autumn Leaves'
                        && $songs[0]['url'] === 'https://example.com/autumn-leaves'
                        && $songs[1]['title'] === 'Stella by Starlight';
                })
            );
    }

    public function test_invitation_displays_duration_times()
    {
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'sub@example.com',
        ]);

        $response = $this->get("/sub/invitation/{$eventSub->invitation_key}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('event.start_time', '20:00:00')
                ->where('event.end_time', '23:00:00')
            );
    }

    public function test_invitation_handles_missing_optional_fields_gracefully()
    {
        // Create event without charts/songs
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $eventWithoutCharts = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
            'title' => 'Simple Gig',
            'additional_data' => null,
        ]);

        $eventSub = EventSubs::create([
            'event_id' => $eventWithoutCharts->id,
            'band_id' => $this->band->id,
            'email' => 'sub@example.com',
            // No band_role_id, payout_amount, or notes
        ]);

        $response = $this->get("/sub/invitation/{$eventSub->invitation_key}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('roleName', null)
                ->where('eventSub.payout_amount', null)
                ->where('charts', [])
                ->where('songs', [])
            );
    }

    public function test_invitation_extracts_charts_from_rehearsal_events()
    {
        // Create a rehearsal event (charts directly in additional_data)
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $rehearsalEvent = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(3),
            'title' => 'Rehearsal',
            'additional_data' => [
                'charts' => [
                    ['id' => 1, 'title' => 'Rehearsal Chart 1'],
                    ['id' => 2, 'title' => 'Rehearsal Chart 2'],
                ],
                'songs' => [
                    ['title' => 'Rehearsal Song'],
                ],
            ],
        ]);

        $eventSub = EventSubs::create([
            'event_id' => $rehearsalEvent->id,
            'band_id' => $this->band->id,
            'email' => 'sub@example.com',
        ]);

        $response = $this->get("/sub/invitation/{$eventSub->invitation_key}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('charts', function ($charts) {
                    return count($charts) === 2
                        && $charts[0]['title'] === 'Rehearsal Chart 1'
                        && $charts[1]['title'] === 'Rehearsal Chart 2';
                })
                ->where('songs', function ($songs) {
                    return count($songs) === 1
                        && $songs[0]['title'] === 'Rehearsal Song';
                })
            );
    }

    public function test_event_sub_with_role_has_role_name_accessor()
    {
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'band_role_id' => $this->bandRole->id,
            'email' => 'sub@example.com',
        ]);

        // Test the model accessor
        $this->assertEquals('Alto Saxophone', $eventSub->role_name);
    }

    public function test_event_sub_without_role_returns_null_role_name()
    {
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'sub@example.com',
        ]);

        // Test the model accessor returns null when no role
        $this->assertNull($eventSub->role_name);
    }
}
