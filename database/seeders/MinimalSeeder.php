<?php

namespace Database\Seeders;

use App\Models\State;
use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class MinimalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries =[
            [
            'id'=>1,
            'sort'=>'AF',
            'country_name'=>'Afghanistan',
            'phoneCode'=>93
            ],
            [
            'id'=>231,
            'sort'=>'US',
            'country_name'=>'United States',
            'phoneCode'=>1
            ],
        ];

        Country::insert($countries);


        $states =[            [
                'state_id' => 17,
                'country_id'=>231,
                'state_name' => 'Kansas'
                
            ],
            [
                'state_id' => 18,
                'country_id'=>231,
                'state_name' => 'Kentucky'
                
            ],
            [
                'state_id' => 19,
                'country_id'=>231,
                'state_name' => 'Louisiana'
                
            ],
        ];

        State::insert($states);
    }
}
