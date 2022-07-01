<?php

namespace Tests\Unit;

use App\Models\Bands;
use App\Models\ProposalPayments;
use App\Models\Proposals;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class PaymentTest extends TestCase
{
    // use RefreshDatabase;
    public function test_formattedPayment()
    {
        $payment = ProposalPayments::factory()->create();

        $this->assertEquals(number_format($payment->amount/100,2),$payment->formattedPaymentAmount);

    }

    public function test_proposalReturnsLatestPayment()
    {
        $payment = ProposalPayments::factory()->create();
        $testAmount = 1000;
        ProposalPayments::factory([
            'amount'=>$testAmount,
            'proposal_id'=>$payment->proposal->id
        ])->create();
        
        
        $this->assertEquals($payment->proposal->lastPayment->amount,$testAmount);
    }
    
}
