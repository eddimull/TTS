<?php

namespace Tests\Unit;

use Mockery;
use Stripe\Event;
use Tests\TestCase;
use App\Models\Bookings;
use App\Models\Invoices;
use App\Models\Payments;
use Stripe\PaymentIntent;
use Illuminate\Http\Request;
use App\Services\FinanceServices;
use App\Services\InvoiceServices;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\StripeWebhookController;
use App\Models\Contacts;

class StripeWebhookControllerTest extends TestCase
{
    protected $controller;
    protected $financeServicesMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new StripeWebhookController();
        $this->financeServicesMock = Mockery::mock(FinanceServices::class);
        $this->app->instance(FinanceServices::class, $this->financeServicesMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testHandleInvoicePaidEvent()
    {
        // Mock Stripe Event
        $stripeEvent = new Event();
        $stripeEvent->type = 'invoice.paid';
        $stripeEvent->data = new \stdClass();
        $stripeEvent->data->object = new \stdClass();
        $stripeEvent->data->object->id = 'inv_123';
        $stripeEvent->data->object->status = 'paid';
        $stripeEvent->data->object->amount_paid = 10000; // $100.00

        // Mock Invoice
        // $invoice = Mockery::mock(Payments::class);
        // $invoice->shouldReceive('getAttribute')->with('booking')->andReturn(new Bookings());
        // $invoice->shouldReceive('save')->once();

        // Mock Request
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($stripeEvent));

        // Expectations
        // Invoices::shouldReceive('where->firstOrFail')->once()->andReturn($invoice);
        // $this->financeServicesMock->shouldReceive('makePayment')->once();

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIgnoreUnexpectedEventType()
    {
        // Mock Stripe Event
        $stripeEvent = new Event();
        $stripeEvent->type = 'unexpected.event';

        // Mock Request
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($stripeEvent));

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandleConvenienceFee()
    {
        // Mock Stripe Event
        $stripeEvent = new Event();
        $stripeEvent->type = 'invoice.paid';
        $stripeEvent->data = new \stdClass();
        $stripeEvent->data->object = new \stdClass();
        $stripeEvent->data->object->id = 'inv_123';
        $stripeEvent->data->object->status = 'paid';
        $stripeEvent->data->object->amount_paid = 10000; // $100.00

        $booking = Bookings::factory()->create();
        $booking->contacts()->attach(
            Contacts::factory()->create(),
            [
                'role' => 'test role',
                'is_primary' => true,
                'notes' => ''
            ]
        );

        $localInvoice = Invoices::create([
            'booking_id' => $booking->id,
            'amount' => $booking->price,
            'status' => 'open',
            'stripe_id' => 'inv_123',
            'convenience_fee' => 100,
        ]);
        $booking->payments()->create([
            'amount' => $booking->price / 100, // this gets cast to Price class which handles multiplying by 100 already
            'status' => 'pending',
            'invoices_id' => $localInvoice->id,
            'name' => $booking->name . ', invoice ' . $localInvoice->id,
            'band_id' => $booking->band_id,
            'user_id' => 1,
        ]);
        // Mock Invoice with convenience fee
        // $invoice = Mockery::mock(Invoices::class);
        // $invoice->shouldReceive('getAttribute')->with('booking')->andReturn(new Bookings());
        // $invoice->shouldReceive('getAttribute')->with('convenience_fee')->andReturn(true);
        // $invoice->shouldReceive('save')->once();

        // Mock Request
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($stripeEvent));

        // Expectations

        // Act

        $response = $this->controller->index($request);
        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvalidStripeSignature()
    {
        Config::set('services.stripe.webhook_secret', 'test_secret');
        $payload = json_encode(['type' => 'test.event']);
        $request = new Request(
            [], // query params
            [], // request params
            [], // attributes
            [], // cookies
            [], // files
            [
                'HTTP_STRIPE_SIGNATURE' => 'invalid_signature'
            ],
            $payload // Add the raw body content
        );

        $this->expectException(\Stripe\Exception\SignatureVerificationException::class);

        $this->controller->index($request);
    }
}
