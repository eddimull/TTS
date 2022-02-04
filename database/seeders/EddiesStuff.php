<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Bands;
use App\Models\BandOwners;
use CountriesTableSeeder;
use StatesTableSeeder;
use EventTypeSeeder;
use ProposalPhasesSeeder;
use Illuminate\Cache\DatabaseStore;

class EddiesStuff extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            'name' => 'Eddie Muller',
            'email'=>'eddimull@gmail.com',
            'password'=>'$2y$10$fKSg384iukPTIEfYPDcjoumuMssSPqr/AGEqA1gvoHX7uIApokMHS'
        ]);
        $band = Bands::create([
            'name' => 'Three Thirty Seven',
            'site_name' => 'three_thirty_seven_test'
        ]);
        BandOwners::create([
            'user_id'=>$user->id,
            'band_id'=>$band->id
        ]);
        \App\Models\Proposals::factory(300)->create(['band_id'=>$band->id]);
        // $dbSeeder = new DatabaseSeeder();
        // $dbSeeder->run();

    }
}
