<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\UserEventsService;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class UserEventsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserEventsService $service;
    protected User $user;
    protected Bands $band;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UserEventsService();

        // Create test user and band
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();

        // Associate user with band as owner
        $this->user->bandOwner()->attach($this->band->id);

        // Authenticate user
        Auth::login($this->user);
    }

    public function test_get_upcoming_charts_returns_empty_collection_when_no_bands()
    {
        // Arrange: Create user with no bands
        $userWithNoBands = User::factory()->create();
        Auth::login($userWithNoBands);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_get_upcoming_charts_returns_charts_from_booking_events()
    {
        // Arrange
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'venue_name' => 'Test Venue'
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'title' => 'Test Event',
            'date' => now()->addDays(7),
            'time' => '19:00:00',
            'additional_data' => (object)[
                'performance' => (object)[
                    'charts' => [
                        (object)[
                            'id' => 1,
                            'title' => 'Test Chart',
                            'composer' => 'Test Composer'
                        ]
                    ]
                ]
            ]
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('chart', $result[0]['type']);
        $this->assertEquals(1, $result[0]['chart_id']);
        $this->assertEquals('Test Chart', $result[0]['title']);
        $this->assertEquals('Test Composer', $result[0]['composer']);
        $this->assertEquals($event->id, $result[0]['event_id']);
        $this->assertEquals('Test Event', $result[0]['event_title']);
        $this->assertEquals('Test Venue', $result[0]['venue_name']);
    }

    public function test_get_upcoming_charts_returns_songs_from_booking_events()
    {
        // Arrange
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'venue_name' => 'Test Venue'
        ]);

        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'title' => 'Test Event',
            'date' => now()->addDays(7),
            'time' => '19:00:00',
            'additional_data' => (object)[
                'performance' => (object)[
                    'songs' => [
                        (object)[
                            'title' => 'Test Song',
                            'url' => 'https://youtube.com/watch?v=test'
                        ]
                    ]
                ]
            ]
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('song', $result[0]['type']);
        $this->assertNull($result[0]['chart_id']);
        $this->assertEquals('Test Song', $result[0]['title']);
        $this->assertEquals('https://youtube.com/watch?v=test', $result[0]['url']);
    }

    public function test_get_upcoming_charts_returns_items_from_rehearsal_events()
    {
        // Arrange
        $schedule = RehearsalSchedule::factory()->create([
            'band_id' => $this->band->id,
            'frequency' => 'weekly',
        ]);

        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id' => $this->band->id,
            'venue_name' => 'Rehearsal Studio',
            'additional_data' => (object)[
                'charts' => [
                    (object)[
                        'id' => 10,
                        'title' => 'Rehearsal Chart',
                        'composer' => 'Rehearsal Composer'
                    ]
                ],
                'songs' => [
                    (object)[
                        'title' => 'Rehearsal Song',
                        'url' => 'https://youtube.com/watch?v=rehearsal'
                    ]
                ]
            ]
        ]);

        Events::factory()->create([
            'eventable_id' => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'title' => 'Weekly Rehearsal',
            'date' => now()->addDays(7),
            'time' => '19:00:00',
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(2, $result);

        // Check chart from rehearsal
        $chartItem = $result->firstWhere('type', 'chart');
        $this->assertEquals('chart', $chartItem['type']);
        $this->assertEquals(10, $chartItem['chart_id']);
        $this->assertEquals('Rehearsal Chart', $chartItem['title']);
        $this->assertEquals('Rehearsal Composer', $chartItem['composer']);
        $this->assertEquals('Rehearsal Studio', $chartItem['venue_name']);

        // Check song from rehearsal
        $songItem = $result->firstWhere('type', 'song');
        $this->assertEquals('song', $songItem['type']);
        $this->assertEquals('Rehearsal Song', $songItem['title']);
        $this->assertEquals('https://youtube.com/watch?v=rehearsal', $songItem['url']);
    }

    public function test_get_upcoming_charts_excludes_past_events()
    {
        // Arrange: Create past event
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date' => now()->subDays(7),
            'additional_data' => (object)[
                'performance' => (object)[
                    'charts' => [
                        (object)['id' => 1, 'title' => 'Past Chart']
                    ]
                ]
            ]
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_get_upcoming_charts_sorts_by_date_and_time()
    {
        // Arrange: Create events in different order
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);

        $laterEvent = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date' => now()->addDays(10),
            'time' => '20:00:00',
            'additional_data' => (object)[
                'performance' => (object)[
                    'charts' => [(object)['id' => 2, 'title' => 'Later Chart']]
                ]
            ]
        ]);

        $earlierEvent = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date' => now()->addDays(5),
            'time' => '18:00:00',
            'additional_data' => (object)[
                'performance' => (object)[
                    'charts' => [(object)['id' => 1, 'title' => 'Earlier Chart']]
                ]
            ]
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('Earlier Chart', $result[0]['title']);
        $this->assertEquals('Later Chart', $result[1]['title']);
    }

    public function test_get_upcoming_charts_handles_multiple_charts_and_songs_per_event()
    {
        // Arrange
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date' => now()->addDays(7),
            'additional_data' => (object)[
                'performance' => (object)[
                    'charts' => [
                        (object)['id' => 1, 'title' => 'Chart 1'],
                        (object)['id' => 2, 'title' => 'Chart 2']
                    ],
                    'songs' => [
                        (object)['title' => 'Song 1', 'url' => 'http://url1'],
                        (object)['title' => 'Song 2', 'url' => 'http://url2']
                    ]
                ]
            ]
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(4, $result);
        $this->assertEquals('chart', $result[0]['type']);
        $this->assertEquals('chart', $result[1]['type']);
        $this->assertEquals('song', $result[2]['type']);
        $this->assertEquals('song', $result[3]['type']);
    }

    public function test_get_upcoming_charts_handles_events_without_performance_data()
    {
        // Arrange
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date' => now()->addDays(7),
            'additional_data' => (object)[]
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_get_upcoming_charts_works_for_band_members()
    {
        // Arrange: Create new user as member (not owner)
        $member = User::factory()->create();
        $member->bandMember()->attach($this->band->id);
        Auth::login($member);

        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date' => now()->addDays(7),
            'additional_data' => (object)[
                'performance' => (object)[
                    'charts' => [(object)['id' => 1, 'title' => 'Member Chart']]
                ]
            ]
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Member Chart', $result[0]['title']);
    }

    public function test_get_upcoming_charts_includes_charts_from_multiple_bands()
    {
        // Arrange: Create second band and associate user
        $band2 = Bands::factory()->create();
        $this->user->bandOwner()->attach($band2->id);

        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $booking2 = Bookings::factory()->create(['band_id' => $band2->id]);

        Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date' => now()->addDays(5),
            'additional_data' => (object)[
                'performance' => (object)[
                    'charts' => [(object)['id' => 1, 'title' => 'Band 1 Chart']]
                ]
            ]
        ]);

        Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date' => now()->addDays(7),
            'additional_data' => (object)[
                'performance' => (object)[
                    'charts' => [(object)['id' => 2, 'title' => 'Band 2 Chart']]
                ]
            ]
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_get_upcoming_charts_handles_missing_chart_properties()
    {
        // Arrange
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date' => now()->addDays(7),
            'additional_data' => (object)[
                'performance' => (object)[
                    'charts' => [
                        (object)[] // Chart with no properties
                    ]
                ]
            ]
        ]);

        // Act
        $result = $this->service->getUpcomingCharts();

        // Assert
        $this->assertCount(1, $result);
        $this->assertNull($result[0]['chart_id']);
        $this->assertEquals('Untitled', $result[0]['title']);
        $this->assertNull($result[0]['composer']);
    }
}
