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
        [
            'name'=>'draft'
        ],
        [
            'name'=>'finalized'
        ],
        [
            'name'=>'sent/pending'
        ],
        [
            'name'=>'approved'
        ],        
        ];
        
        ProposalPhases::insert($proposal_phases);
    }
}
