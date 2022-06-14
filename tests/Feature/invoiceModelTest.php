<?php

namespace Tests\Feature;

use App\Models\Invoices;
use App\Models\Proposals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class invoiceModelTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_invoice_returns_proposal()
    {
        $proposal = Proposals::factory()->create();

        $invoice = Invoices::factory()->create([
            'proposal_id'=>$proposal->id
        ]);

        $this->assertEquals($proposal->name,$invoice->proposal->name);
    }
}
