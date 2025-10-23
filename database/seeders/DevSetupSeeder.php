<?php

namespace Database\Seeders;

use App\Models\BandEvents;
use App\Models\Proposals;
use App\Models\RehearsalSchedule;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Bands;
use App\Models\BandOwners;

class DevSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            'name' => 'Admin',
            'email'=>'admin@example.com',
            'password'=>'$2y$10$9qoA9D9VwXtszzBAF/D4aetJNzpbVI8/5fTtFm.RktK9lCKGSbNcq' // password
        ]);
        $this->command->info('Admin user (admin@example.com) created with password "password"');

        $band = Bands::create([
            'name' => 'Test Band',
            'site_name' => 'test_band'
        ]);
        BandOwners::create([
            'user_id'=>$user->id,
            'band_id'=>$band->id
        ]);
        Proposals::factory(300)->create(['band_id' => $band->id]);
        BandEvents::factory(50)->create(['band_id' => $band->id]);

        // Create rehearsal schedules
        RehearsalSchedule::create([
            'band_id' => $band->id,
            'name' => 'Weekly Practice',
            'description' => 'Regular weekly practice sessions',
            'frequency' => 'weekly',
            'day_of_week' => 'tuesday',
            'default_time' => '19:00:00',
            'location_name' => 'Band Practice Space',
            'location_address' => '123 Music St, New Orleans, LA 70115',
            'notes' => 'Bring your gear and be ready to rock!',
            'active' => true,
        ]);

        RehearsalSchedule::create([
            'band_id' => $band->id,
            'name' => 'Thursday Jam',
            'description' => 'Casual jam sessions',
            'frequency' => 'biweekly',
            'day_of_week' => 'thursday',
            'default_time' => '20:00:00',
            'location_name' => 'Studio B',
            'location_address' => '456 Jazz Ave, New Orleans, LA 70116',
            'active' => true,
        ]);

        $this->command->info('Created 2 rehearsal schedules for Test Band');

    }
}
