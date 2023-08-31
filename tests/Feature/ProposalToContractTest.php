<?php

namespace Tests\Feature;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Proposals;
use App\Models\User;
use App\Services\ProposalServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProposalToContractTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_can_create_proposal()
    {       
        $proposal = Proposals::factory()->hasProposalContacts()->create([
            'phase_id'=>2
        ]);

        ProposalServices::straightToContract($proposal);
        
        $this->assertDatabaseHas('proposals',[
            'id'=>$proposal->id,
            'phase_id'=>5
        ]);

        $this->assertDatabaseHas('contracts',[
            'proposal_id'=>$proposal->id
        ]);

    }
}
