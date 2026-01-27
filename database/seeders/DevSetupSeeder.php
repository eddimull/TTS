<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Bands;
use App\Models\BandOwners;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DevSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates minimal core data for development:
     * - Admin user
     * - Test band
     * - Band owner relationship
     *
     * For comprehensive test data (bookings, members, roles, payments, etc.),
     * use the interactive command instead:
     *
     *   php artisan dev:setup --all
     *
     * Or run the command without arguments for an interactive menu.
     *
     * @return void
     */
    public function run()
    {
        // Seed event types first if they don't exist
        if (\App\Models\EventTypes::count() === 0) {
            $this->call(EventTypeSeeder::class);
        }

        // Create admin user
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => '$2y$10$9qoA9D9VwXtszzBAF/D4aetJNzpbVI8/5fTtFm.RktK9lCKGSbNcq' // password
            ]
        );

        // Assign site-admin role for Horizon access
        $siteAdminRole = Role::findOrCreate('site-admin', 'web');
        if (!$user->hasRole('site-admin')) {
            $user->assignRole($siteAdminRole);
        }

        $this->command->info('✓ Admin user created: admin@example.com / password');
        $this->command->info('✓ site-admin role assigned (Horizon access granted)');

        // Create test band
        $band = Bands::firstOrCreate(
            ['site_name' => 'test_band'],
            ['name' => 'Test Band']
        );

        // Link admin as band owner
        BandOwners::firstOrCreate([
            'user_id' => $user->id,
            'band_id' => $band->id
        ]);

        $this->command->info('✓ Test Band created with admin as owner');
        $this->command->newLine();

        // Direct users to the comprehensive setup command
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('💡 For complete test data setup, run:');
        $this->command->line('');
        $this->command->line('   <fg=green>php artisan dev:setup --all</>');
        $this->command->line('');
        $this->command->line('   Or run without arguments for interactive menu:');
        $this->command->line('');
        $this->command->line('   <fg=green>php artisan dev:setup</>');
        $this->command->line('');
        $this->command->line('   This will create:');
        $this->command->line('   • Band members and roles');
        $this->command->line('   • Event rosters');
        $this->command->line('   • Bookings with events');
        $this->command->line('   • Partial payments');
        $this->command->line('   • Contacts for bookings');
        $this->command->line('   • Rehearsal schedules');
        $this->command->line('   • Payout configurations');
        $this->command->line('   • Stripe test accounts');
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
