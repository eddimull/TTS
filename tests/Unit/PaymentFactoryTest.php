<?php

namespace Tests\Unit;

use App\Models\ProposalPayments;
use App\Models\Proposals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFactoryTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function test_payment_creates_proper_dependencies()
    {
        ProposalPayments::factory()->count(15)->create();
        $payment = ProposalPayments::factory()->create();
        $proposal = $payment->proposal;
        $band = $proposal->band;
        $owners = $band->owners;
        $author = $proposal->author;

        $this->assertEquals($owners[0]->user->id,$author->id);

    }
}
