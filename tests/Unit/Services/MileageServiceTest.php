<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MileageService;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\BandEvents;
use App\Models\Events;
use App\Models\EventMember;
use App\Models\EventDistanceForMembers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MileageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Bands $band;
    protected int $stateId = 14; // Illinois

    protected function setUp(): void
    {
        parent::setUp();

        // Insert a state row so MileageService can look up state_name
        DB::table('states')->insert([
            'state_id' => $this->stateId,
            'state_name' => 'Illinois',
            'country_id' => 231,
        ]);

        $this->user = User::factory()->create([
            'Address1' => '123 Main St',
            'City'     => 'Chicago',
            'StateID'  => $this->stateId,
            'Zip'      => '60601',
        ]);

        $this->band = Bands::factory()->create([
            'address' => '456 Band Ave',
            'city'    => 'Chicago',
            'state'   => 'IL',
            'zip'     => '60602',
        ]);

        Auth::setUser($this->user);
    }

    // -------------------------------------------------------------------------
    // resolveOrigin
    // -------------------------------------------------------------------------

    public function test_resolves_origin_from_user_address()
    {
        $service = new MileageService();
        $origin = $this->callProtected($service, 'resolveOrigin', [$this->user, $this->band]);

        $this->assertStringContainsString('123 Main St', $origin);
        $this->assertStringContainsString('Chicago', $origin);
        $this->assertStringContainsString('Illinois', $origin);
        $this->assertStringContainsString('60601', $origin);
    }

    public function test_falls_back_to_band_address_when_user_has_no_address()
    {
        $userWithNoAddress = User::factory()->create([
            'Address1' => null,
            'City'     => null,
            'StateID'  => null,
            'Zip'      => null,
        ]);
        Auth::setUser($userWithNoAddress);

        $service = new MileageService();
        $origin = $this->callProtected($service, 'resolveOrigin', [$userWithNoAddress, $this->band]);

        $this->assertStringContainsString('456 Band Ave', $origin);
        $this->assertStringContainsString('Chicago', $origin);
        $this->assertStringContainsString('IL', $origin);
        $this->assertStringContainsString('60602', $origin);
    }

    public function test_returns_null_when_neither_user_nor_band_has_address()
    {
        $userWithNoAddress = User::factory()->create([
            'Address1' => null,
            'City'     => null,
            'StateID'  => null,
            'Zip'      => null,
        ]);
        $bandWithNoAddress = Bands::factory()->create([
            'address' => null,
            'city'    => null,
            'state'   => null,
            'zip'     => null,
        ]);

        $service = new MileageService();
        $origin = $this->callProtected($service, 'resolveOrigin', [$userWithNoAddress, $bandWithNoAddress]);

        $this->assertNull($origin);
    }

    // -------------------------------------------------------------------------
    // resolveDestination
    // -------------------------------------------------------------------------

    public function test_resolves_destination_from_booking_venue_address()
    {
        $booking = Bookings::factory()->create([
            'band_id'       => $this->band->id,
            'venue_name'    => 'The Venue',
            'venue_address' => '789 Venue St, Springfield, IL 62701',
        ]);

        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking->id,
            'date'           => Carbon::now()->subDay(),
        ]);
        $event->load('eventable');

        $service = new MileageService();
        $destination = $this->callProtected($service, 'resolveDestination', [$event]);

        $this->assertEquals('789 Venue St, Springfield, IL 62701', $destination);
    }

    public function test_returns_null_destination_when_booking_has_no_venue_address()
    {
        $booking = Bookings::factory()->create([
            'band_id'       => $this->band->id,
            'venue_address' => null,
        ]);

        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking->id,
            'date'           => Carbon::now()->subDay(),
        ]);
        $event->load('eventable');

        $service = new MileageService();
        $destination = $this->callProtected($service, 'resolveDestination', [$event]);

        $this->assertNull($destination);
    }

    public function test_resolves_destination_from_band_event_split_address_fields()
    {
        DB::table('states')->insert([
            'state_id'   => 99,
            'state_name' => 'Missouri',
            'country_id' => 231,
        ]);

        $bandEvent = BandEvents::factory()->create([
            'band_id'        => $this->band->id,
            'address_street' => '100 Show Blvd',
            'city'           => 'St. Louis',
            'state_id'       => 99,
            'zip'            => '63101',
        ]);

        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\BandEvents',
            'eventable_id'   => $bandEvent->id,
            'date'           => Carbon::now()->subDay(),
        ]);
        $event->load('eventable');

        $service = new MileageService();
        $destination = $this->callProtected($service, 'resolveDestination', [$event]);

        $this->assertStringContainsString('100 Show Blvd', $destination);
        $this->assertStringContainsString('St. Louis', $destination);
        $this->assertStringContainsString('Missouri', $destination);
        $this->assertStringContainsString('63101', $destination);
    }

    // -------------------------------------------------------------------------
    // handle — skips when no usable origin
    // -------------------------------------------------------------------------

    public function test_skips_calculation_when_no_origin_can_be_resolved()
    {
        $userWithNoAddress = User::factory()->create([
            'Address1' => null,
            'City'     => null,
            'StateID'  => null,
            'Zip'      => null,
        ]);
        $bandWithNoAddress = Bands::factory()->create([
            'address' => null,
            'city'    => null,
            'state'   => null,
            'zip'     => null,
        ]);
        Auth::setUser($userWithNoAddress);

        $booking = Bookings::factory()->create([
            'band_id'       => $bandWithNoAddress->id,
            'venue_address' => '789 Venue St, Springfield, IL 62701',
        ]);
        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking->id,
            'date'           => Carbon::now()->subDay(),
        ]);
        $event->load('eventable');

        $service = new MileageService();
        $result = $service->handle(collect([$event]), $bandWithNoAddress);

        $this->assertEquals(0, $result['miles']);
        $this->assertDatabaseMissing('event_distance_for_members', [
            'event_id' => $event->id,
            'user_id'  => $userWithNoAddress->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // handle — uses cached distance record
    // -------------------------------------------------------------------------

    public function test_uses_cached_distance_and_does_not_call_api_when_record_is_fresh()
    {
        $booking = Bookings::factory()->create([
            'band_id'       => $this->band->id,
            'venue_address' => '789 Venue St, Springfield, IL 62701',
        ]);
        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking->id,
            'date'           => Carbon::now()->subDay(),
            'updated_at'     => Carbon::now()->subDays(5),
        ]);

        // Pre-existing fresh distance record (created after event updated_at)
        EventDistanceForMembers::create([
            'user_id'  => $this->user->id,
            'event_id' => $event->id,
            'miles'    => 75,
            'minutes'  => 90,
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(2),
        ]);

        $event->load('eventable');

        // GoogleMaps facade should NOT be called — if it were, it would throw
        // because we haven't mocked it. The test passing proves the cache was used.
        $service = new MileageService();
        $result = $service->handle(collect([$event]), $this->band);

        $this->assertEquals(75, $result['miles']);
    }

    // -------------------------------------------------------------------------
    // UserStatsService integration — mileage by year
    // -------------------------------------------------------------------------

    public function test_sums_mileage_correctly_across_multiple_events()
    {
        DB::table('band_members')->insert([
            'user_id'    => $this->user->id,
            'band_id'    => $this->band->id,
            'created_at' => Carbon::now()->subYear(),
            'updated_at' => Carbon::now()->subYear(),
        ]);

        $booking1 = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date'    => Carbon::now()->subDays(10),
        ]);
        $booking2 = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date'    => Carbon::now()->subDays(20),
        ]);

        $event1 = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking1->id,
            'date'           => $booking1->date,
        ]);
        $event2 = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking2->id,
            'date'           => $booking2->date,
        ]);

        EventDistanceForMembers::create(['user_id' => $this->user->id, 'event_id' => $event1->id, 'miles' => 100, 'minutes' => 120]);
        EventDistanceForMembers::create(['user_id' => $this->user->id, 'event_id' => $event2->id, 'miles' => 50,  'minutes' => 60]);

        $service = new \App\Services\UserStatsService($this->user);
        $stats = $service->getUserStats();

        $this->assertEquals(150, $stats['travel']['total_miles']);
        $this->assertEquals(180, $stats['travel']['total_minutes']);
        $this->assertEquals(3.0, $stats['travel']['total_hours']);
        $this->assertEquals(2, $stats['travel']['event_count']);
    }

    public function test_breaks_mileage_down_by_year()
    {
        DB::table('band_members')->insert([
            'user_id'    => $this->user->id,
            'band_id'    => $this->band->id,
            'created_at' => Carbon::now()->subYears(3),
            'updated_at' => Carbon::now()->subYears(3),
        ]);

        $booking2023 = Bookings::factory()->create(['band_id' => $this->band->id, 'date' => Carbon::create(2023, 6, 1)]);
        $booking2024 = Bookings::factory()->create(['band_id' => $this->band->id, 'date' => Carbon::create(2024, 6, 1)]);
        $booking2024b = Bookings::factory()->create(['band_id' => $this->band->id, 'date' => Carbon::create(2024, 9, 1)]);

        $event2023  = Events::factory()->create(['eventable_type' => 'App\\Models\\Bookings', 'eventable_id' => $booking2023->id,  'date' => $booking2023->date]);
        $event2024  = Events::factory()->create(['eventable_type' => 'App\\Models\\Bookings', 'eventable_id' => $booking2024->id,  'date' => $booking2024->date]);
        $event2024b = Events::factory()->create(['eventable_type' => 'App\\Models\\Bookings', 'eventable_id' => $booking2024b->id, 'date' => $booking2024b->date]);

        EventDistanceForMembers::create(['user_id' => $this->user->id, 'event_id' => $event2023->id,  'miles' => 200, 'minutes' => 240]);
        EventDistanceForMembers::create(['user_id' => $this->user->id, 'event_id' => $event2024->id,  'miles' => 100, 'minutes' => 120]);
        EventDistanceForMembers::create(['user_id' => $this->user->id, 'event_id' => $event2024b->id, 'miles' => 50,  'minutes' => 60]);

        $service = new \App\Services\UserStatsService($this->user);
        $stats = $service->getUserStats();

        $byYear = collect($stats['travel']['by_year']);
        $this->assertCount(2, $byYear);

        // Years should be sorted descending
        $this->assertEquals(2024, $byYear[0]['year']);
        $this->assertEquals(2023, $byYear[1]['year']);

        $year2024 = $byYear->firstWhere('year', 2024);
        $this->assertEquals(150, $year2024['total_miles']);
        $this->assertEquals(3.0, $year2024['total_hours']);
        $this->assertEquals(2, $year2024['event_count']);

        $year2023 = $byYear->firstWhere('year', 2023);
        $this->assertEquals(200, $year2023['total_miles']);
        $this->assertEquals(4.0, $year2023['total_hours']);
        $this->assertEquals(1, $year2023['event_count']);
    }

    public function test_excludes_absent_events_from_mileage_total()
    {
        DB::table('band_members')->insert([
            'user_id'    => $this->user->id,
            'band_id'    => $this->band->id,
            'created_at' => Carbon::now()->subYear(),
            'updated_at' => Carbon::now()->subYear(),
        ]);

        $attendedBooking = Bookings::factory()->create(['band_id' => $this->band->id, 'date' => Carbon::now()->subDays(5)]);
        $absentBooking   = Bookings::factory()->create(['band_id' => $this->band->id, 'date' => Carbon::now()->subDays(10)]);

        $attendedEvent = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $attendedBooking->id,
            'date'           => $attendedBooking->date,
        ]);
        $absentEvent = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $absentBooking->id,
            'date'           => $absentBooking->date,
        ]);

        // Mark user as absent from the second event
        EventMember::factory()->create([
            'event_id'          => $absentEvent->id,
            'band_id'           => $this->band->id,
            'user_id'           => $this->user->id,
            'attendance_status' => 'absent',
        ]);

        EventDistanceForMembers::create(['user_id' => $this->user->id, 'event_id' => $attendedEvent->id, 'miles' => 100, 'minutes' => 120]);
        EventDistanceForMembers::create(['user_id' => $this->user->id, 'event_id' => $absentEvent->id,   'miles' => 200, 'minutes' => 240]);

        $service = new \App\Services\UserStatsService($this->user);
        $stats = $service->getUserStats();

        // Only the attended event's miles should count
        $this->assertEquals(100, $stats['travel']['total_miles']);
        $this->assertEquals(1, $stats['travel']['event_count']);
    }

    public function test_excludes_excused_events_from_mileage_total()
    {
        DB::table('band_members')->insert([
            'user_id'    => $this->user->id,
            'band_id'    => $this->band->id,
            'created_at' => Carbon::now()->subYear(),
            'updated_at' => Carbon::now()->subYear(),
        ]);

        $booking = Bookings::factory()->create(['band_id' => $this->band->id, 'date' => Carbon::now()->subDays(5)]);
        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking->id,
            'date'           => $booking->date,
        ]);

        EventMember::factory()->create([
            'event_id'          => $event->id,
            'band_id'           => $this->band->id,
            'user_id'           => $this->user->id,
            'attendance_status' => 'excused',
        ]);

        EventDistanceForMembers::create(['user_id' => $this->user->id, 'event_id' => $event->id, 'miles' => 150, 'minutes' => 180]);

        $service = new \App\Services\UserStatsService($this->user);
        $stats = $service->getUserStats();

        $this->assertEquals(0, $stats['travel']['total_miles']);
        $this->assertEquals(0, $stats['travel']['event_count']);
    }

    public function test_includes_event_with_no_event_member_record_as_attended()
    {
        DB::table('band_members')->insert([
            'user_id'    => $this->user->id,
            'band_id'    => $this->band->id,
            'created_at' => Carbon::now()->subYear(),
            'updated_at' => Carbon::now()->subYear(),
        ]);

        $booking = Bookings::factory()->create(['band_id' => $this->band->id, 'date' => Carbon::now()->subDays(5)]);
        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking->id,
            'date'           => $booking->date,
        ]);

        // No EventMember record — should be treated as attended
        EventDistanceForMembers::create(['user_id' => $this->user->id, 'event_id' => $event->id, 'miles' => 80, 'minutes' => 90]);

        $service = new \App\Services\UserStatsService($this->user);
        $stats = $service->getUserStats();

        $this->assertEquals(80, $stats['travel']['total_miles']);
        $this->assertEquals(1, $stats['travel']['event_count']);
    }

    public function test_does_not_count_another_users_distance_records()
    {
        $otherUser = User::factory()->create();

        DB::table('band_members')->insert([
            'user_id'    => $this->user->id,
            'band_id'    => $this->band->id,
            'created_at' => Carbon::now()->subYear(),
            'updated_at' => Carbon::now()->subYear(),
        ]);

        $booking = Bookings::factory()->create(['band_id' => $this->band->id, 'date' => Carbon::now()->subDays(5)]);
        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking->id,
            'date'           => $booking->date,
        ]);

        // Only the other user has a distance record
        EventDistanceForMembers::create(['user_id' => $otherUser->id, 'event_id' => $event->id, 'miles' => 300, 'minutes' => 360]);

        $service = new \App\Services\UserStatsService($this->user);
        $stats = $service->getUserStats();

        $this->assertEquals(0, $stats['travel']['total_miles']);
    }

    public function test_returns_empty_by_year_when_no_distances_recorded()
    {
        DB::table('band_members')->insert([
            'user_id'    => $this->user->id,
            'band_id'    => $this->band->id,
            'created_at' => Carbon::now()->subYear(),
            'updated_at' => Carbon::now()->subYear(),
        ]);

        $booking = Bookings::factory()->create(['band_id' => $this->band->id, 'date' => Carbon::now()->subDays(5)]);
        Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking->id,
            'date'           => $booking->date,
        ]);

        $service = new \App\Services\UserStatsService($this->user);
        $stats = $service->getUserStats();

        // event_count and by_year still reflect attended events even without distance records
        $this->assertEquals(1, $stats['travel']['event_count']);
        $this->assertEquals(0, $stats['travel']['total_miles']);
        $this->assertCount(1, $stats['travel']['by_year']);
        $this->assertEquals(0, $stats['travel']['by_year'][0]['total_miles']);
        $this->assertEquals(1, $stats['travel']['by_year'][0]['event_count']);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    protected function callProtected(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($object, $args);
    }
}
