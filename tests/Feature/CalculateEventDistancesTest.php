<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\EventDistanceForMembers;
use App\Jobs\CalculateEventDistances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalculateEventDistancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed states for address formatting
        $this->seed(\Database\Seeders\StatesTableSeeder::class);
        
        // Mock Google Maps to avoid actual API calls
        // Return realistic Google Maps Distance Matrix API response
        \GoogleMaps::shouldReceive('load')
            ->with('distancematrix')
            ->andReturnSelf();
        
        \GoogleMaps::shouldReceive('setParamByKey')
            ->andReturnSelf();
        
        \GoogleMaps::shouldReceive('get')
            ->andReturn(json_encode([
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => [
                                    'text' => '150 mi',
                                    'value' => 241401  // meters
                                ],
                                'duration' => [
                                    'text' => '3 hours 15 mins',
                                    'value' => 11700  // seconds
                                ]
                            ]
                        ]
                    ]
                ]
            ]));
    }

    public function test_job_is_dispatched_when_event_is_created()
    {
        Queue::fake();

        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        
        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'date' => Carbon::now()->addDays(10),
        ]);

        // The EventObserver dispatches ProcessEventCreated when an event is created
        Queue::assertPushed(\App\Jobs\ProcessEventCreated::class);
    }

    public function test_calculates_distance_for_band_members_with_complete_addresses()
    {
        $band = Bands::factory()->create();
        
        // Create user with complete address
        $user = User::factory()->create([
            'Address1' => '123 Main St',
            'City' => 'Los Angeles',
            'StateID' => 5, // California
            'Zip' => '90001'
        ]);

        // Add user as band member
        DB::table('band_members')->insert([
            'user_id' => $user->id,
            'band_id' => $band->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Create booking with venue address
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'venue_name' => 'The Roxy Theatre',
            'venue_address' => '9009 Sunset Blvd, West Hollywood, CA 90069',
        ]);

        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'date' => Carbon::now()->addDays(10),
        ]);

        // Run the job
        $job = new CalculateEventDistances($event);
        $job->handle();

        // Check that distance was calculated
        $distance = EventDistanceForMembers::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->first();

        $this->assertNotNull($distance);
        $this->assertEquals(150, $distance->miles);
        $this->assertEquals(195, $distance->minutes); // 3 hours 15 mins = 195 mins
    }

    public function test_skips_users_without_complete_addresses()
    {
        $band = Bands::factory()->create();
        
        // Create user with incomplete address
        $user = User::factory()->create([
            'Address1' => '123 Main St',
            'City' => null, // Missing city
            'StateID' => 5,
            'Zip' => '90001'
        ]);

        DB::table('band_members')->insert([
            'user_id' => $user->id,
            'band_id' => $band->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'venue_name' => 'The Roxy Theatre',
            'venue_address' => '9009 Sunset Blvd, West Hollywood, CA 90069',
        ]);

        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'date' => Carbon::now()->addDays(10),
        ]);

        $job = new CalculateEventDistances($event);
        $job->handle();

        // No distance should be calculated
        $distance = EventDistanceForMembers::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->first();

        $this->assertNull($distance);
    }

    public function test_recalculates_distance_if_event_updated()
    {
        $band = Bands::factory()->create();
        
        $user = User::factory()->create([
            'Address1' => '123 Main St',
            'City' => 'Los Angeles',
            'StateID' => 5,
            'Zip' => '90001'
        ]);

        DB::table('band_members')->insert([
            'user_id' => $user->id,
            'band_id' => $band->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'venue_name' => 'The Roxy Theatre',
            'venue_address' => '9009 Sunset Blvd, West Hollywood, CA 90069',
        ]);

        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'date' => Carbon::now()->addDays(10),
        ]);

        // First calculation
        $job = new CalculateEventDistances($event);
        $job->handle();

        $distance = EventDistanceForMembers::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->first();

        $this->assertNotNull($distance);
        $firstCalculationTime = $distance->updated_at;

        // Sleep to ensure timestamps are different (database precision might be seconds)
        sleep(1);

        // Update the event (touch it to update timestamp)
        $event->touch();
        $event = $event->fresh();

        // Recalculate
        $job = new CalculateEventDistances($event);
        $job->handle();

        $distance = $distance->fresh();

        // Should have recalculated (updated_at changed)
        $this->assertNotEquals($firstCalculationTime, $distance->updated_at);
    }

    public function test_calculates_distance_for_both_owners_and_members()
    {
        $band = Bands::factory()->create();
        
        $owner = User::factory()->create([
            'Address1' => '123 Main St',
            'City' => 'Los Angeles',
            'StateID' => 5,
            'Zip' => '90001'
        ]);

        $member = User::factory()->create([
            'Address1' => '456 Oak Ave',
            'City' => 'Los Angeles',
            'StateID' => 5,
            'Zip' => '90002'
        ]);

        DB::table('band_owners')->insert([
            'user_id' => $owner->id,
            'band_id' => $band->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('band_members')->insert([
            'user_id' => $member->id,
            'band_id' => $band->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'venue_name' => 'The Roxy Theatre',
            'venue_address' => '9009 Sunset Blvd, West Hollywood, CA 90069',
        ]);

        $event = Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'date' => Carbon::now()->addDays(10),
        ]);

        $job = new CalculateEventDistances($event);
        $job->handle();

        // Both should have distances calculated
        $ownerDistance = EventDistanceForMembers::where('user_id', $owner->id)
            ->where('event_id', $event->id)
            ->first();

        $memberDistance = EventDistanceForMembers::where('user_id', $member->id)
            ->where('event_id', $event->id)
            ->first();

        $this->assertNotNull($ownerDistance);
        $this->assertNotNull($memberDistance);
    }
}
