<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\StripeWebhookController;
use App\Models\Invoices;
use App\Models\Proposals;
use App\Services\FinanceServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Mockery;
use Stripe\Event;
use Stripe\PaymentIntent;

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
        $invoice = Mockery::mock(Invoices::class);
        $invoice->shouldReceive('getAttribute')->with('proposal')->andReturn(new Proposals());
        $invoice->shouldReceive('save')->once();

        // Mock Request
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($stripeEvent));

        // Expectations
        Invoices::shouldReceive('where->firstOrFail')->once()->andReturn($invoice);
        $this->financeServicesMock->shouldReceive('makePayment')->once();

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

        // Mock Invoice with convenience fee
        $invoice = Mockery::mock(Invoices::class);
        $invoice->shouldReceive('getAttribute')->with('proposal')->andReturn(new Proposals());
        $invoice->shouldReceive('getAttribute')->with('convenience_fee')->andReturn(true);
        $invoice->shouldReceive('save')->once();

        // Mock Request
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($stripeEvent));

        // Expectations
        Invoices::shouldReceive('where->firstOrFail')->once()->andReturn($invoice);
        $this->financeServicesMock->shouldReceive('makePayment')->once()->withArgs(function ($proposal, $name, $amount)
        {
            // Check if the amount is correctly calculated with convenience fee
            return $amount < 100.00; // The amount should be less than the original due to fee deductions
        });

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvalidStripeSignature()
    {
        Config::set('services.stripe.webhook_secret', 'test_secret');

        $request = new Request([], [], [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 'invalid_signature'
        ]);

        $this->expectException(\Stripe\Exception\SignatureVerificationException::class);

        $this->controller->index($request);
    }
}
