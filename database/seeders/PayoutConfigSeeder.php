<?php

namespace Database\Seeders;

use App\Models\Bands;
use App\Models\BandPayoutConfig;
use App\Models\BandPaymentGroup;
use Illuminate\Database\Seeder;

class PayoutConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating default payout configurations...');
        
        $band = Bands::where('name', 'Test Band')->first();
        
        if (!$band) {
            $this->command->error('Test Band not found. Please run DevSetupSeeder first.');
            return;
        }

        // Create payment groups
        $this->command->info('Creating payment groups...');
        
        $players = BandPaymentGroup::create([
            'band_id' => $band->id,
            'name' => 'Players',
            'description' => 'Band musicians and performers',
            'default_payout_type' => 'equal_split',
            'default_payout_value' => null,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $soundCrew = BandPaymentGroup::create([
            'band_id' => $band->id,
            'name' => 'Sound Crew',
            'description' => 'Sound engineers and audio technicians',
            'default_payout_type' => 'fixed',
            'default_payout_value' => 500.00,
            'display_order' => 2,
            'is_active' => true,
        ]);

        $lightingCrew = BandPaymentGroup::create([
            'band_id' => $band->id,
            'name' => 'Lighting',
            'description' => 'Lighting technicians',
            'default_payout_type' => 'fixed',
            'default_payout_value' => 400.00,
            'display_order' => 3,
            'is_active' => true,
        ]);

        $dancers = BandPaymentGroup::create([
            'band_id' => $band->id,
            'name' => 'Dancers',
            'description' => 'Performance dancers',
            'default_payout_type' => 'fixed',
            'default_payout_value' => 300.00,
            'display_order' => 4,
            'is_active' => true,
        ]);

        // Add owners and members to Players group
        foreach ($band->owners as $owner) {
            $players->users()->attach($owner->user_id, [
                'payout_type' => 'equal_split',
                'payout_value' => null,
                'notes' => 'Band owner',
            ]);
        }

        foreach ($band->members as $member) {
            $players->users()->attach($member->user_id, [
                'payout_type' => 'equal_split',
                'payout_value' => null,
                'notes' => 'Band member',
            ]);
        }

        $this->command->info('✓ Created 4 payment groups with members');

        // Create a default equal split configuration (old style)
        BandPayoutConfig::create([
            'band_id' => $band->id,
            'name' => 'Equal Split - Standard',
            'is_active' => false,
            'band_cut_type' => 'percentage',
            'band_cut_value' => 10.00, // 10% for band expenses
            'member_payout_type' => 'equal_split',
            'tier_config' => null,
            'regular_member_count' => 0,
            'production_member_count' => 2, // Sound engineer and lighting tech
            'member_specific_config' => null,
            'include_owners' => true,
            'include_members' => true,
            'minimum_payout' => 100.00, // Minimum $100 per person
            'notes' => 'Standard configuration: 10% band cut for expenses, equal split among all members and production crew',
            'use_payment_groups' => false,
            'payment_group_config' => null,
        ]);

        // Create a payment group-based configuration
        BandPayoutConfig::create([
            'band_id' => $band->id,
            'name' => 'Group-Based - Standard Gig',
            'is_active' => true,
            'band_cut_type' => 'percentage',
            'band_cut_value' => 10.00, // 10% for band expenses
            'member_payout_type' => 'equal_split', // Not used when using payment groups
            'tier_config' => null,
            'regular_member_count' => 0,
            'production_member_count' => 0,
            'member_specific_config' => null,
            'include_owners' => true,
            'include_members' => true,
            'minimum_payout' => 100.00,
            'notes' => 'Group-based configuration: 10% band cut, 60% to players (equal split), fixed rates for crew',
            'use_payment_groups' => true,
            'payment_group_config' => [
                [
                    'group_id' => $players->id,
                    'allocation_type' => 'percentage',
                    'allocation_value' => 60, // 60% split among players
                ],
                [
                    'group_id' => $soundCrew->id,
                    'allocation_type' => 'percentage',
                    'allocation_value' => 15, // 15% for sound crew (split among them)
                ],
                [
                    'group_id' => $lightingCrew->id,
                    'allocation_type' => 'percentage',
                    'allocation_value' => 12, // 12% for lighting
                ],
                [
                    'group_id' => $dancers->id,
                    'allocation_type' => 'percentage',
                    'allocation_value' => 13, // 13% for dancers
                ],
            ],
        ]);

        // Create a tiered configuration (old style)
        BandPayoutConfig::create([
            'band_id' => $band->id,
            'name' => 'Tiered - Wedding Rate',
            'is_active' => false,
            'band_cut_type' => 'fixed',
            'band_cut_value' => 500.00, // Fixed $500 for band
            'member_payout_type' => 'tiered',
            'tier_config' => [
                [
                    'min' => 0,
                    'max' => 5000,
                    'type' => 'percentage',
                    'value' => 12 // 12% per member for bookings under $5k
                ],
                [
                    'min' => 5001,
                    'max' => 10000,
                    'type' => 'percentage',
                    'value' => 15 // 15% per member for bookings $5k-$10k
                ],
                [
                    'min' => 10001,
                    'max' => 999999,
                    'type' => 'percentage',
                    'value' => 18 // 18% per member for bookings over $10k
                ]
            ],
            'regular_member_count' => 0,
            'production_member_count' => 2,
            'member_specific_config' => null,
            'include_owners' => true,
            'include_members' => true,
            'minimum_payout' => 150.00,
            'notes' => 'Wedding configuration: Fixed $500 band cut, tiered payouts based on total booking amount',
            'use_payment_groups' => false,
            'payment_group_config' => null,
        ]);

        $this->command->info('✓ Created 3 payout configurations for Test Band');
        $this->command->info('✓ Visit /finances/payout-calculator to see them in action!');
    }
}
