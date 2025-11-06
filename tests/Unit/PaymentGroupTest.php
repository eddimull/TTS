<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\User;
use App\Models\BandPaymentGroup;
use App\Models\BandPayoutConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentGroupTest extends TestCase
{
    use RefreshDatabase;

    protected $band;
    protected $users;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a band
        $this->band = Bands::factory()->create(['name' => 'Test Band']);
        
        // Create users
        $this->users = User::factory()->count(5)->create();
    }

    public function test_can_create_payment_group()
    {
        $group = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Sound Crew',
            'description' => 'Audio engineers',
            'default_payout_type' => 'fixed',
            'default_payout_value' => 500.00,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('band_payment_groups', [
            'band_id' => $this->band->id,
            'name' => 'Sound Crew',
        ]);

        $this->assertEquals('Sound Crew', $group->name);
        $this->assertEquals(500.00, $group->default_payout_value);
    }

    public function test_can_add_users_to_payment_group()
    {
        $group = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Players',
            'default_payout_type' => 'equal_split',
        ]);

        $group->users()->attach($this->users[0]->id, [
            'payout_type' => 'equal_split',
            'notes' => 'Lead guitar',
        ]);

        $group->users()->attach($this->users[1]->id, [
            'payout_type' => 'fixed',
            'payout_value' => 600.00,
            'notes' => 'Guest vocalist',
        ]);

        $this->assertEquals(2, $group->users()->count());
        $this->assertDatabaseHas('band_payment_group_members', [
            'band_payment_group_id' => $group->id,
            'user_id' => $this->users[0]->id,
        ]);
    }

    public function test_payment_group_calculation_with_equal_split()
    {
        $group = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Players',
            'default_payout_type' => 'equal_split',
        ]);

        // Add 3 users with equal split
        foreach (array_slice($this->users->all(), 0, 3) as $user) {
            $group->users()->attach($user->id);
        }

        $result = $group->calculateGroupPayout(3000.00);

        $this->assertEquals(3, $result['member_count']);
        $this->assertEquals(3000.00, $result['total']);
        
        // Each member should get $1000
        foreach ($result['payouts'] as $payout) {
            $this->assertEquals(1000.00, $payout['amount']);
        }
    }

    public function test_payment_group_calculation_with_mixed_types()
    {
        $group = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Mixed Group',
            'default_payout_type' => 'equal_split',
        ]);

        // User 1: Fixed $600
        $group->users()->attach($this->users[0]->id, [
            'payout_type' => 'fixed',
            'payout_value' => 600.00,
        ]);

        // User 2: Percentage 15%
        $group->users()->attach($this->users[1]->id, [
            'payout_type' => 'percentage',
            'payout_value' => 15,
        ]);

        // User 3 & 4: Equal split (default)
        $group->users()->attach($this->users[2]->id);
        $group->users()->attach($this->users[3]->id);

        $result = $group->calculateGroupPayout(3000.00);

        // Fixed: $600
        // Percentage: $450 (15% of $3000)
        // Remaining for equal split: $3000 - $600 - $450 = $1950
        // Each equal split member: $975

        $this->assertEquals(4, $result['member_count']);
        
        $payouts = collect($result['payouts']);
        $fixedPayout = $payouts->where('user_id', $this->users[0]->id)->first();
        $percentagePayout = $payouts->where('user_id', $this->users[1]->id)->first();
        $equalSplit1 = $payouts->where('user_id', $this->users[2]->id)->first();

        $this->assertEquals(600.00, $fixedPayout['amount']);
        $this->assertEquals(450.00, $percentagePayout['amount']);
        $this->assertEquals(975.00, $equalSplit1['amount']);
    }

    public function test_payout_config_with_payment_groups()
    {
        // Create two groups
        $players = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Players',
            'default_payout_type' => 'equal_split',
            'display_order' => 1,
        ]);

        $crew = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Crew',
            'default_payout_type' => 'equal_split', // Changed to equal_split so allocation is distributed
            'display_order' => 2,
        ]);

        // Add users to groups
        $players->users()->attach($this->users[0]->id);
        $players->users()->attach($this->users[1]->id);
        $crew->users()->attach($this->users[2]->id);

        // Create payout config
        $config = BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Test Config',
            'is_active' => true,
            'band_cut_type' => 'percentage',
            'band_cut_value' => 10.00,
            'use_payment_groups' => true,
            'payment_group_config' => [
                [
                    'group_id' => $players->id,
                    'allocation_type' => 'percentage',
                    'allocation_value' => 70, // 70% to players
                ],
                [
                    'group_id' => $crew->id,
                    'allocation_type' => 'percentage',
                    'allocation_value' => 30, // 30% to crew
                ],
            ],
        ]);

        $result = $config->calculatePayouts(5000.00);

        // SEQUENTIAL ALLOCATION:
        // Band cut: $500 (10%)
        // Distributable: $4500
        // 
        // 1. Players (first in order) get 70% of $4500 = $3150
        //    Remaining: $4500 - $3150 = $1350
        // 2. Crew (second in order) get 30% of REMAINING $1350 = $405
        //    Each member splits: $3150 / 2 = $1575 for players, $405 for crew

        $this->assertEquals(5000.00, $result['total_amount']);
        $this->assertEquals(500.00, $result['band_cut']);
        $this->assertEquals(4500.00, $result['distributable_amount']);
        
        $this->assertCount(2, $result['payment_group_payouts']);
        
        // Players should split $3150 equally (2 members = $1575 each)
        $playersGroup = collect($result['payment_group_payouts'])->where('group_id', $players->id)->first();
        $this->assertEquals(3150.00, $playersGroup['total']);
        $this->assertCount(2, $playersGroup['payouts']);
        
        // Crew gets 30% of REMAINING ($1350) = $405 total (1 member with equal_split gets the full allocation)
        $crewGroup = collect($result['payment_group_payouts'])->where('group_id', $crew->id)->first();
        $this->assertEquals(405.00, $crewGroup['total']);
        $this->assertCount(1, $crewGroup['payouts']);
        $this->assertEquals(405.00, $crewGroup['payouts'][0]['amount']);
    }

    public function test_user_can_only_be_in_group_once()
    {
        $group = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Test Group',
            'default_payout_type' => 'equal_split',
        ]);

        $group->users()->attach($this->users[0]->id);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        $group->users()->attach($this->users[0]->id); // Should fail due to unique constraint
    }

    public function test_sequential_allocation_of_payment_groups()
    {
        // Create groups in specific order
        $production = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Production',
            'default_payout_type' => 'fixed',
            'default_payout_value' => 700.00,
            'display_order' => 1, // First
            'is_active' => true,
        ]);

        $players = BandPaymentGroup::create([
            'band_id' => $this->band->id,
            'name' => 'Players',
            'default_payout_type' => 'equal_split',
            'display_order' => 2, // Second (gets what's left after production)
            'is_active' => true,
        ]);

        // Add 1 user to production
        $production->users()->attach($this->users[0]->id);

        // Add 4 users to players
        $players->users()->attach($this->users[1]->id);
        $players->users()->attach($this->users[2]->id);
        $players->users()->attach($this->users[3]->id);
        $players->users()->attach($this->users[4]->id);

        // Create payout config
        $config = BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Sequential Test',
            'is_active' => true,
            'band_cut_type' => 'percentage',
            'band_cut_value' => 10.00, // 10% band cut
            'use_payment_groups' => true,
            'payment_group_config' => [
                [
                    'group_id' => $production->id,
                    'allocation_type' => 'fixed',
                    'allocation_value' => 700, // Fixed $700
                ],
                [
                    'group_id' => $players->id,
                    'allocation_type' => 'percentage',
                    'allocation_value' => 100, // 100% of REMAINING (after production)
                ],
            ],
        ]);

        // Calculate: $5000 booking
        $result = $config->calculatePayouts(5000.00);

        // Expected calculation:
        // Total: $5000
        // Band cut (10%): $500
        // Distributable: $4500
        // 
        // Sequential allocation:
        // 1. Production gets fixed $700
        // 2. Remaining: $4500 - $700 = $3800
        // 3. Players get 100% of remaining = $3800
        // 4. Players split equally: $3800 / 4 = $950 each

        $this->assertEquals(5000.00, $result['total_amount']);
        $this->assertEquals(500.00, $result['band_cut']);
        $this->assertEquals(4500.00, $result['distributable_amount']);

        // Production group
        $productionGroup = collect($result['payment_group_payouts'])->where('group_id', $production->id)->first();
        $this->assertEquals(700.00, $productionGroup['total']);
        $this->assertEquals(700.00, $productionGroup['payouts'][0]['amount']);

        // Players group - should split remaining $3800
        $playersGroup = collect($result['payment_group_payouts'])->where('group_id', $players->id)->first();
        $this->assertEquals(3800.00, $playersGroup['total']);
        $this->assertCount(4, $playersGroup['payouts']);
        
        foreach ($playersGroup['payouts'] as $payout) {
            $this->assertEquals(950.00, $payout['amount']);
        }

        // Total member payout should be $4500 (all distributable)
        $this->assertEquals(4500.00, $result['total_member_payout']);
        
        // No remaining (100% allocated)
        $this->assertEquals(0.00, $result['remaining']);
    }
}
