<?php

namespace Database\Seeders;

use App\Models\Bands;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\BandPaymentGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestBandMembersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Adding members to Test Band...');
        
        $band = Bands::where('name', 'Test Band')->first();
        
        if (!$band) {
            $this->command->error('Test Band not found. Please run DevSetupSeeder first.');
            return;
        }

        // Create several test users
        $users = [
            ['name' => 'John Smith', 'email' => 'john@example.com'],
            ['name' => 'Sarah Johnson', 'email' => 'sarah@example.com'],
            ['name' => 'Mike Williams', 'email' => 'mike@example.com'],
            ['name' => 'Emily Davis', 'email' => 'emily@example.com'],
            ['name' => 'Chris Brown', 'email' => 'chris@example.com'],
            ['name' => 'Jessica Wilson', 'email' => 'jessica@example.com'],
            ['name' => 'David Martinez', 'email' => 'david@example.com'],
            ['name' => 'Amanda Garcia', 'email' => 'amanda@example.com'],
            ['name' => 'Tom Anderson', 'email' => 'tom@example.com'],
            ['name' => 'Lisa Taylor', 'email' => 'lisa@example.com'],
        ];

        $createdUsers = [];
        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => bcrypt('password'),
                ]
            );
            $createdUsers[] = $user;
        }

        // Add some as band members
        foreach (array_slice($createdUsers, 0, 5) as $user) {
            BandMembers::firstOrCreate([
                'band_id' => $band->id,
                'user_id' => $user->id,
            ]);
        }

        $this->command->info('✓ Created 10 users, 5 added as band members');

        // Get payment groups
        $playersGroup = BandPaymentGroup::where('band_id', $band->id)
            ->where('name', 'Players')
            ->first();
        
        $soundGroup = BandPaymentGroup::where('band_id', $band->id)
            ->where('name', 'Sound Crew')
            ->first();
        
        $lightingGroup = BandPaymentGroup::where('band_id', $band->id)
            ->where('name', 'Lighting')
            ->first();
        
        $dancersGroup = BandPaymentGroup::where('band_id', $band->id)
            ->where('name', 'Dancers')
            ->first();

        if (!$playersGroup || !$soundGroup || !$lightingGroup || !$dancersGroup) {
            $this->command->error('Payment groups not found. Please run PayoutConfigSeeder first.');
            return;
        }

        // Assign users to payment groups with different configurations
        
        // Players (4 members with equal split and one with custom percentage)
        $playersGroup->users()->syncWithoutDetaching([
            $createdUsers[0]->id => [
                'payout_type' => 'equal_split',
                'notes' => 'Lead guitar',
            ],
            $createdUsers[1]->id => [
                'payout_type' => 'equal_split',
                'notes' => 'Bass',
            ],
            $createdUsers[2]->id => [
                'payout_type' => 'equal_split',
                'notes' => 'Drums',
            ],
            $createdUsers[3]->id => [
                'payout_type' => 'percentage',
                'payout_value' => 20,
                'notes' => 'Vocals - Featured performer',
            ],
        ]);

        // Sound Crew (2 members with fixed amounts)
        $soundGroup->users()->syncWithoutDetaching([
            $createdUsers[4]->id => [
                'payout_type' => 'fixed',
                'payout_value' => 600.00,
                'notes' => 'Lead sound engineer',
            ],
            $createdUsers[5]->id => [
                'payout_type' => 'fixed',
                'payout_value' => 400.00,
                'notes' => 'Assistant engineer',
            ],
        ]);

        // Lighting (2 members, one using group default, one with custom)
        $lightingGroup->users()->syncWithoutDetaching([
            $createdUsers[6]->id => [
                'payout_type' => null, // Use group default
                'notes' => 'Lighting designer',
            ],
            $createdUsers[7]->id => [
                'payout_type' => 'fixed',
                'payout_value' => 450.00,
                'notes' => 'Senior lighting tech',
            ],
        ]);

        // Dancers (2 members with equal split)
        $dancersGroup->users()->syncWithoutDetaching([
            $createdUsers[8]->id => [
                'payout_type' => 'equal_split',
                'notes' => 'Lead dancer',
            ],
            $createdUsers[9]->id => [
                'payout_type' => 'equal_split',
                'notes' => 'Backup dancer',
            ],
        ]);

        $this->command->info('✓ Assigned users to payment groups:');
        $this->command->info('  - Players: 4 members (3 equal split, 1 percentage)');
        $this->command->info('  - Sound Crew: 2 members (fixed amounts)');
        $this->command->info('  - Lighting: 2 members (1 default, 1 custom)');
        $this->command->info('  - Dancers: 2 members (equal split)');
        $this->command->info('');
        $this->command->info('Test credentials:');
        $this->command->info('  Email: john@example.com (or any other created user)');
        $this->command->info('  Password: password');
        $this->command->info('');
        $this->command->info('Visit /finances/payout-calculator to test the frontend!');
    }
}
