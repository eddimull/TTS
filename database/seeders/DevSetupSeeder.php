<?php

namespace Database\Seeders;

use App\Models\BandEvents;
use App\Models\Proposals;
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

    }
}
