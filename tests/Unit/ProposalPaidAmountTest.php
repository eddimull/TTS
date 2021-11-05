<?php

namespace Tests\Unit;

use App\Models\Bands;
use App\Models\ProposalPayments;
use App\Models\Proposals;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class ProposalPaidAmount extends TestCase
{
    // use RefreshDatabase;
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function test_can_get_paid_amount()
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();
        // dd($band->id);
        $proposal = Proposals::factory()->create([
            'band_id'=>$band->id,
            'price'=>'10000.00',
            'paid'=>false,
            'author_id'=>$user->id
        ]);
        
        ProposalPayments::create([
            'amount'=>50000,
            'name'=>'Test',
            'proposal_id'=>$proposal->id
        ]);
        // dd(ProposalPayments::all());
        $amountPaid = $proposal->amountPaid;

        $this->assertEquals(500,$amountPaid);
    }

    public function testCanGetAmountPaidWithMultiplePayments()
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();
        // dd($band->id);
        $proposal = Proposals::factory()->create([
            'band_id'=>$band->id,
            'price'=>'10000.00',
            'paid'=>false,
            'author_id'=>$user->id
        ]);
        
        ProposalPayments::create([
            'amount'=>50000,
            'name'=>'Test',
            'proposal_id'=>$proposal->id
        ]);
        ProposalPayments::create([
            'amount'=>10000,
            'name'=>'Test',
            'proposal_id'=>$proposal->id
        ]);
        // dd(ProposalPayments::all());
        $amountPaid = $proposal->amountPaid;

        $this->assertEquals(600,$amountPaid);
    }
}
