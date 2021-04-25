<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use CountriesTableSeeder;
use StatesTableSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        $this->call(CountriesTableSeeder::class);
        $this->call(StatesTableSeeder::class);
        $this->call(EventTypeSeeder::class);
    }
}
