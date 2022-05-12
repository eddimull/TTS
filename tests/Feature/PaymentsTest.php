<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Proposals;
use App\Models\BandOwners;
use App\Models\ProposalPayments;
use Carbon\Carbon;

class PaymentsTest extends TestCase
{
    
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_addPayment()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create([
            'band_id'=>$band->id,
            'user_id'=>$user->id
        ]);
        $proposal = Proposals::factory()->create([
            'band_id'=>$band->id,
            'author_id'=>$user->id,
            'phase_id'=>6
        ]);
        $paymentName = 'Test Payment';
        $response = $this->actingAs($user)->post('/proposals/' . $proposal->key . '/payment',
            [
                'name'=>$paymentName,
                'amount'=>1000,
                'paymentDate'=>Carbon::now()
            ]);

        $response->assertSessionHas(['successMessage']);
        $this->assertDatabaseHas('payments',[
            'name'=>$paymentName,
            'proposal_id'=>$proposal->id
        ]);
    }
    public function test_deletePayment()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create([
            'band_id'=>$band->id,
            'user_id'=>$user->id
        ]);
        $proposal = Proposals::factory()->create([
            'band_id'=>$band->id,
            'author_id'=>$user->id,
            'phase_id'=>6
        ]);
        $paymentName = 'Should Be Deleted ' . Carbon::now()->timestamp;
        $payment = ProposalPayments::create([
            'proposal_id'=>$proposal->id,
            'name'=>$paymentName,
            'amount'=>1000,
            'paymentDate'=>Carbon::now()
        ]);

        $response = $this->actingAs($user)->delete('/proposals/' . $proposal->key . '/deletePayment/' . $payment->id);
        
        $response->assertSessionHas(['successMessage']);

        $this->assertDatabaseMissing('payments',[
            'name'=>$paymentName,
            'proposal_id'=>$proposal->id
        ]);
    }
}
