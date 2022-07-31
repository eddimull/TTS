<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use App\Models\Bands;
use App\Models\Proposals;
use App\Models\ProposalPayments as Payments;
use App\Models\BandOwners;
use App\Models\User;
use Tests\TestCase;

class showPaymentsForBandTest extends TestCase
{
    /**
     * gets a list of payments for a band
     *
     * @return void
     */
    public function test_getAllPaymentsForBand()
    {
        $payments = 10;
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create([
            'band_id'=>$band->id,
            'user_id'=>$user->id
        ]);
        $proposals = Proposals::factory()->count($payments)->create([
            'band_id'=>$band->id,
            'author_id'=>$user->id,
            'phase_id'=>6
        ])->each(function($proposal){
            Payments::factory()->create([
                'proposal_id'=>$proposal->id,
                'name'=>'Test Payment',
                'amount'=>1000,
                'paymentDate'=>Carbon::now()
            ]);
        });


        $this->assertEquals($payments, $band->payments->count());

    }
}
