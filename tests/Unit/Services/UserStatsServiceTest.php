<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\UserStatsService;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\BandPayoutConfig;
use App\Models\BandPaymentGroup;
use App\Models\Events;
use App\Models\EventDistanceForMembers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Bands $band;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
    }

    public function test_it_calculates_user_join_date_from_band_owner_pivot()
    {
        $joinDate = Carbon::now()->subMonths(6);

        DB::table('band_owners')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => $joinDate,
            'updated_at' => $joinDate,
        ]);

        $service = new UserStatsService($this->user);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getUserJoinDate');
        $method->setAccessible(true);

        $result = $method->invoke($service, $this->band);

        $this->assertEquals($joinDate->format('Y-m-d H:i:s'), $result->format('Y-m-d H:i:s'));
    }

    
    public function test_it_calculates_user_join_date_from_band_member_pivot()
    {
        $joinDate = Carbon::now()->subMonths(3);

        DB::table('band_members')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => $joinDate,
            'updated_at' => $joinDate,
        ]);

        $service = new UserStatsService($this->user);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getUserJoinDate');
        $method->setAccessible(true);

        $result = $method->invoke($service, $this->band);

        $this->assertEquals($joinDate->format('Y-m-d H:i:s'), $result->format('Y-m-d H:i:s'));
    }

    
    public function test_it_uses_earliest_join_date_when_user_is_both_owner_and_member()
    {
        $ownerDate = Carbon::now()->subMonths(6);
        $memberDate = Carbon::now()->subMonths(3);

        DB::table('band_owners')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => $ownerDate,
            'updated_at' => $ownerDate,
        ]);

        DB::table('band_members')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => $memberDate,
            'updated_at' => $memberDate,
        ]);

        $service = new UserStatsService($this->user);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getUserJoinDate');
        $method->setAccessible(true);

        $result = $method->invoke($service, $this->band);

        // Should use owner date (earlier)
        $this->assertEquals($ownerDate->format('Y-m-d H:i:s'), $result->format('Y-m-d H:i:s'));
    }

    
    public function test_it_calculates_equal_split_when_no_payout_config_exists()
    {
        // Add user as owner
        DB::table('band_owners')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Create two more users as members
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        DB::table('band_members')->insert([
            ['user_id' => $member1->id, 'band_id' => $this->band->id, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['user_id' => $member2->id, 'band_id' => $this->band->id, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);

        // Create a booking
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 3000, // $3000 / 3 members = $1000 each
            'date' => Carbon::now(),
        ]);

        $service = new UserStatsService($this->user);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateUserShareFromBooking');
        $method->setAccessible(true);

        $userShare = $method->invoke($service, $this->band, $booking);

        // Should be 1/3 of $3000 = $1000 = 100000 cents
        $this->assertEquals(100000, $userShare);
    }

    
    public function test_it_calculates_share_using_payment_group_configuration()
    {
        // Add user as member
        DB::table('band_members')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Create payment group
        $paymentGroup = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Players',
            'default_payout_type' => 'percentage',
            'default_payout_value' => 25, // 25% each
            'display_order' => 1,
            'is_active' => true,
        ]);

        // Add user to payment group
        $paymentGroup->users()->attach($this->user->id, [
            'payout_type' => 'percentage',
            'payout_value' => 25,
        ]);

        // Create payout config
        $payoutConfig = BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Default Config',
            'is_active' => true,
            'band_cut_type' => 'percentage',
            'band_cut_value' => 10, // Band keeps 10%
            'use_payment_groups' => true,
            'payment_group_config' => [
                [
                    'group_id' => $paymentGroup->id,
                    'allocation_type' => 'percentage',
                    'allocation_value' => 100, // Group gets 100% of distributable
                ]
            ],
        ]);

        // Create a booking for $1000
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000,
            'date' => Carbon::now(),
        ]);

        $service = new UserStatsService($this->user);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateUserShareFromBooking');
        $method->setAccessible(true);

        $userShare = $method->invoke($service, $this->band, $booking);

        // Band keeps 10% ($100), leaving $900
        // User gets 25% of $900 = $225 = 22500 cents
        $this->assertEquals(22500, $userShare);
    }

    
    public function test_it_only_counts_bookings_after_user_join_date()
    {
        $joinDate = Carbon::now()->subMonths(2);

        DB::table('band_members')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => $joinDate,
            'updated_at' => $joinDate,
        ]);

        // Create booking BEFORE join date (should NOT count)
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000,
            'date' => $joinDate->copy()->subDays(10),
            'status' => 'confirmed',
        ]);

        // Create booking AFTER join date (should count)
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 2000,
            'date' => $joinDate->copy()->addDays(10),
            'status' => 'confirmed',
        ]);

        $service = new UserStatsService($this->user);
        $stats = $service->getUserStats();

        // Should only count 1 booking (the one after join date)
        $this->assertEquals(1, $stats['payments']['booking_count']);
    }

    
    public function test_it_only_counts_confirmed_and_pending_bookings()
    {
        $joinDate = Carbon::now()->subMonth();

        DB::table('band_members')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => $joinDate,
            'updated_at' => $joinDate,
        ]);

        // Create bookings with different statuses
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000,
            'date' => Carbon::now(),
            'status' => 'confirmed',
        ]);

        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000,
            'date' => Carbon::now(),
            'status' => 'pending',
        ]);

        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000,
            'date' => Carbon::now(),
            'status' => 'cancelled', // Should NOT count
        ]);

        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000,
            'date' => Carbon::now(),
            'status' => 'draft', // Should NOT count
        ]);

        $service = new UserStatsService($this->user);
        $stats = $service->getUserStats();

        // Should only count confirmed and pending (2 bookings)
        $this->assertEquals(2, $stats['payments']['booking_count']);
    }

    
    public function test_it_aggregates_earnings_by_year_correctly()
    {
        DB::table('band_members')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => Carbon::now()->subYears(3),
            'updated_at' => Carbon::now()->subYears(3),
        ]);

        // Create another member for equal split
        $member2 = User::factory()->create();
        DB::table('band_members')->insert([
            'user_id' => $member2->id,
            'band_id' => $this->band->id,
            'created_at' => Carbon::now()->subYears(3),
            'updated_at' => Carbon::now()->subYears(3),
        ]);

        // Create bookings in different years
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 2000, // $1000 per member
            'date' => Carbon::create(2023, 6, 15),
            'status' => 'confirmed',
        ]);

        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 4000, // $2000 per member
            'date' => Carbon::create(2024, 3, 20),
            'status' => 'confirmed',
        ]);

        $service = new UserStatsService($this->user);
        $stats = $service->getUserStats();

        $this->assertCount(2, $stats['payments']['by_year']);

        $year2024 = collect($stats['payments']['by_year'])->firstWhere('year', 2024);
        $this->assertEquals('2000.00', $year2024['total']);

        $year2023 = collect($stats['payments']['by_year'])->firstWhere('year', 2023);
        $this->assertEquals('1000.00', $year2023['total']);
    }

    
    public function test_it_aggregates_earnings_by_band_correctly()
    {
        $band2 = Bands::factory()->create();

        // Join both bands
        DB::table('band_members')->insert([
            ['user_id' => $this->user->id, 'band_id' => $this->band->id, 'created_at' => Carbon::now()->subYear(), 'updated_at' => Carbon::now()->subYear()],
            ['user_id' => $this->user->id, 'band_id' => $band2->id, 'created_at' => Carbon::now()->subYear(), 'updated_at' => Carbon::now()->subYear()],
        ]);

        // Create equal split members for band 1
        $member1 = User::factory()->create();
        DB::table('band_members')->insert([
            'user_id' => $member1->id,
            'band_id' => $this->band->id,
            'created_at' => Carbon::now()->subYear(),
            'updated_at' => Carbon::now()->subYear(),
        ]);

        // Create bookings for both bands
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 4000, // $2000 per member (2 members)
            'date' => Carbon::now(),
            'status' => 'confirmed',
        ]);

        Bookings::factory()->create([
            'band_id' => $band2->id,
            'price' => 3000, // $3000 (only member)
            'date' => Carbon::now(),
            'status' => 'confirmed',
        ]);

        $service = new UserStatsService($this->user);
        $stats = $service->getUserStats();

        $this->assertCount(2, $stats['payments']['by_band']);

        $band1Stats = collect($stats['payments']['by_band'])->firstWhere('band_id', $this->band->id);
        $this->assertEquals('2000.00', $band1Stats['total']);

        $band2Stats = collect($stats['payments']['by_band'])->firstWhere('band_id', $band2->id);
        $this->assertEquals('3000.00', $band2Stats['total']);

        // Total should be $5000
        $this->assertEquals('5000.00', $stats['payments']['total_earnings']);
    }

    
    public function test_it_returns_zero_earnings_when_user_not_in_payment_group()
    {
        DB::table('band_members')->insert([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Create payment group but DON'T add user to it
        $paymentGroup = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Players',
            'default_payout_type' => 'equal_split',
            'display_order' => 1,
            'is_active' => true,
        ]);

        // Add different user to payment group
        $otherUser = User::factory()->create();
        $paymentGroup->users()->attach($otherUser->id);

        // Create payout config
        $payoutConfig = BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Default Config',
            'is_active' => true,
            'use_payment_groups' => true,
            'payment_group_config' => [
                [
                    'group_id' => $paymentGroup->id,
                    'allocation_type' => 'percentage',
                    'allocation_value' => 100,
                ]
            ],
        ]);

        // Create a booking
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000,
            'date' => Carbon::now(),
        ]);

        $service = new UserStatsService($this->user);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateUserShareFromBooking');
        $method->setAccessible(true);

        $userShare = $method->invoke($service, $this->band, $booking);

        // User should get $0 because they're not in the payment group
        $this->assertEquals(0, $userShare);
    }

    
    public function test_it_returns_empty_stats_when_user_not_in_any_bands()
    {
        $service = new UserStatsService($this->user);
        $stats = $service->getUserStats();

        $this->assertEquals('0.00', $stats['payments']['total_earnings']);
        $this->assertEquals(0, $stats['payments']['booking_count']);
        $this->assertEmpty($stats['payments']['by_year']);
        $this->assertEmpty($stats['payments']['by_band']);
        $this->assertEquals(0, $stats['travel']['total_miles']);
        $this->assertEmpty($stats['locations']);
    }
}
