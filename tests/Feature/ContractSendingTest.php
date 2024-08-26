<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Proposals;
use App\Models\Bands;
use App\Models\ProposalContacts;
use App\Http\Controllers\ProposalsController;
use App\Services\ProposalServices;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContractSendingTest extends TestCase
{
    use RefreshDatabase;

    public function test_contractCanBeSent()
    {
        Storage::fake('s3');

        $band = Bands::factory()->create();
        $proposal = Proposals::factory()->create(['band_id' => $band->id]);
        ProposalContacts::factory()->forProposal($proposal->id)->create();

        $proposalServices = new ProposalServices($proposal);
        $result = $proposalServices->make_pandadoc_contract($proposal);

        $this->assertTrue($result);
        $this->assertDatabaseHas('contracts', [
            'proposal_id' => $proposal->id,
            'envelope_id' => 'test_document_id',
            'status' => 'sent'
        ]);
    }
}
