<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProposalPhases;

class ProposalPhasesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
       
        $proposal_phases =[
            ['name'=>'Draft'],
            ['name'=>'Finalized'],
            ['name'=>'proposal sent'],
            ['name'=>'Approved'],
            ['name'=>'contract sent'],
            ['name'=>'contract signed'],     
        ];


        
        ProposalPhases::insert($proposal_phases);
    }
}
