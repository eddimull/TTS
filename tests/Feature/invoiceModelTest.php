<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bookings;
use App\Models\Invoices;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class invoiceModelTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_invoice_returns_booking()
    {
        $booking = Bookings::factory()->create();

        $invoice = Invoices::factory()->create([
            'booking_id' => $booking->id
        ]);

        $this->assertEquals($booking->name, $invoice->booking->name);
    }
}
