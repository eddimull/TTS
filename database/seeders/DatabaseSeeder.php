<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\CountriesTableSeeder;
use Database\Seeders\StatesTableSeeder;
use Database\Seeders\EventTypeSeeder;
use Database\Seeders\ProposalPhasesSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::factory(10)->create();
        \App\Models\Bands::factory(10)->create();
        $this->call(CountriesTableSeeder::class);
        $this->call(StatesTableSeeder::class);
        // $this->call(EventTypeSeeder::class); //it calls itself on the migration
        // $this->call(ProposalPhasesSeeder::class);
        \App\Models\Proposals::factory(300)->create();
    }
}
