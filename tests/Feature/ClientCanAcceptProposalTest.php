<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\ProposalContacts;
use App\Models\Proposals;

class ClientCanAcceptProposalTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_canSeeProposal()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create([
            'band_id'=>$band->id,
            'user_id'=>$user->id
        ]);
        $proposal = Proposals::factory()->create([
            'phase_id'=>6,
            'band_id'=>$band->id,
            'author_id'=>$user->id
        ]);

        $response = $this->get('/proposals/' . $proposal->key . '/details');
        // dd($response);
        // $response->assertInertia('Who are we speaking with today?');
        $response->assertStatus(200);
        // $response->assertSessionHas(['successMessage']);
        // $this->assertDatabaseHas('proposal_contacts',[
        //     'proposal_id'=>$proposal->id,
        //     'email'=>'test@usertest.com'
        // ]);
    }

    public function test_canAcceptProposal()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create([
            'band_id'=>$band->id,
            'user_id'=>$user->id
        ]);
        $proposal = Proposals::factory()->create([
            'phase_id'=>6,
            'band_id'=>$band->id,
            'author_id'=>$user->id
        ]);

        ProposalContacts::create([
            'proposal_id'=>$proposal->id,
            'email'=>'test@test.com',
            'name'=>'TESTING',
            'phonenumber'=>'test'
        ]);


        $response = $this->post('/proposals/' . $proposal->key . '/accept',[
            'person'=>'test'
        ]);

        // dd($response);
        // $response->assertStatus(302); //really need that inertia plugin...
        $this->assertDatabaseHas('contracts',[
            'proposal_id'=>$proposal->id
        ]);

    }
}
