<?php

namespace Tests\Unit;

use Mockery;
use Stripe\Event;
use Tests\TestCase;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Invoices;
use App\Models\Payments;
use Stripe\PaymentIntent;
use App\Enums\PaymentType;
use Illuminate\Http\Request;
use App\Services\FinanceServices;
use App\Services\InvoiceServices;
use App\Services\PdfGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use App\Http\Controllers\StripeWebhookController;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;
    protected $financeServicesMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new StripeWebhookController();
        $this->financeServicesMock = Mockery::mock(FinanceServices::class);
        $this->app->instance(FinanceServices::class, $this->financeServicesMock);

        $pdfMock = Mockery::mock(PdfGeneratorService::class);
        $pdfMock->shouldReceive('generateFromHtml')->andReturn('%PDF-1.4 fake');
        $pdfMock->shouldReceive('fromUrl')->andReturnSelf();
        $pdfMock->shouldReceive('pdf')->andReturn('%PDF-1.4 fake');
        $this->app->instance(PdfGeneratorService::class, $pdfMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
            'payment_type' => PaymentType::Invoice->value,
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

    public function testCheckoutSessionCompletedRecordsContactPayment()
    {
        // Regression for TTS-BAND-2W: a checkout.session.completed event with
        // valid metadata must record a payment against the booking. The bug was
        // a (array) cast on the Stripe Session that dropped the nested metadata,
        // causing "Missing metadata in checkout session" and no payment.
        Queue::fake();

        $booking = Bookings::factory()->create();
        $contact = Contacts::factory()->create(['band_id' => $booking->band_id]);
        $booking->contacts()->attach($contact, [
            'role' => 'client',
            'is_primary' => true,
            'notes' => '',
        ]);

        // A real StripeObject (not a stdClass) so the toArray()-vs-(array)
        // distinction is actually exercised — metadata is a nested StripeObject.
        $stripeEvent = new Event();
        $stripeEvent->type = 'checkout.session.completed';
        $stripeEvent->data = new \stdClass();
        $stripeEvent->data->object = \Stripe\Checkout\Session::constructFrom([
            'id' => 'cs_live_test_2w',
            'payment_intent' => 'pi_test_2w',
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'contact_id' => (string) $contact->id,
                'payment_amount' => '450000', // cents
            ],
        ]);

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($stripeEvent));

        $response = $this->controller->index($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('payments', [
            'payable_id' => $booking->id,
            'payable_type' => Bookings::class,
            'payer_type' => Contacts::class,
            'payer_id' => $contact->id,
            'payment_type' => 'portal',
            'status' => 'paid',
        ]);
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
