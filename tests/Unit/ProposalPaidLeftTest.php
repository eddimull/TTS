<?php

namespace Tests\Unit;

use App\Models\Bands;
use App\Models\ProposalPayments;
use App\Models\Proposals;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class ProposalPaidLeft extends TestCase
{
    // use RefreshDatabase;
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
        $amountLeft = $proposal->amountLeft;

        $this->assertEquals(number_format(10000 - 600,2),$amountLeft);
    }

    public function testCanGetTotalFormattedPrice()
    {
        $proposal = Proposals::factory()->create();
        $this->assertEquals(number_format(floatval($proposal->price),2),$proposal->formattedPrice);
    }
    
}
