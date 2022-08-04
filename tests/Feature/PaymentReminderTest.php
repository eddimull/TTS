<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentReminderTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_canGetAllProposals3WeeksOut()
    {
        Proposals::factory()->count(10)->create();

        $proposals = Proposals::where('payment status','not paid')->and('performance_date' < Carbon::parse('+3 weeks'))->get();
        
        $this->assertTrue($proposals->count() == 10);
    }
}
