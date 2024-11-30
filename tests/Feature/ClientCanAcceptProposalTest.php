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
use Illuminate\Support\Facades\Storage;

class ClientCanAcceptProposalTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_canSeeProposal()
    {
        $proposal = Proposals::factory()->create([
            'phase_id' => 6,
        ]);

        $response = $this->get('/proposals/' . $proposal->key . '/details');

        $response->assertStatus(200);
    }

    public function test_canAcceptProposal()
    {
        Storage::fake('s3');

        $proposal = Proposals::factory()->hasProposalContacts()->create([
            'phase_id' => 6
        ]);

        $response = $this->post('/proposals/' . $proposal->key . '/accept', [
            'person' => 'test'
        ]);

        $response->assertStatus(302); //really need that inertia plugin...
        $this->assertDatabaseHas('proposal_contracts', [
            'proposal_id' => $proposal->id
        ]);
    }
}
