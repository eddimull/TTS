<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EventTypes;

class EventTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
       
        $event_types =[
        [
            'name'=>'Wedding'
        ],
        [
            'name'=>'Bar Gig'
        ],
        [
            'name'=>'Casino'
        ],
        [
            'name'=>'Special Event'
        ],
        [
            'name'=>'Charity'
        ],
        [
            'name'=>'Festival'
        ],
        [
            'name'=>'Private Party'
        ],
        [
            'name'=>'Mardi Gras Ball'
        ],        
        [
            'name'=>'Other'
        ],
        [
            'name'=>'Rehearsal'
        ]
        ];
        
        EventTypes::insert($event_types);
    }
}
