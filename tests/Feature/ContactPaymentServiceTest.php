<?php

namespace Tests\Feature;

use App\Contracts\StripeClientInterface;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\StripeAccounts;
use App\Services\ContactPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\StripeObject;
use Tests\TestCase;

class ContactPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Example test showing how to mock Stripe client for testing
     */
    public function test_create_checkout_session_with_mocked_stripe_client()
    {
        // Arrange: Create necessary test data
        $booking = Bookings::factory()->create([
            'name' => 'Test Booking',
        ]);

        $contact = Contacts::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test Contact',
            'phone' => '555-1234',
        ]);

        StripeAccounts::create([
            'band_id' => $booking->band_id,
            'stripe_account_id' => 'acct_1234567890',
            'status' => 'active',
        ]);

        // Create a mock Stripe client
        $mockStripeClient = Mockery::mock(StripeClientInterface::class);

        // Mock the retrieveAccount call (not called for test accounts)
        $mockAccount = StripeObject::constructFrom([
            'id' => 'acct_1234567890',
            'capabilities' => [
                'transfers' => 'active',
            ]
        ]);
        $mockStripeClient->shouldReceive('retrieveAccount')
            ->once()
            ->with('acct_1234567890')
            ->andReturn($mockAccount);

        // Mock the createCustomer call
        $mockCustomer = StripeObject::constructFrom(['id' => 'cus_test_123']);
        $mockStripeClient->shouldReceive('createCustomer')
            ->once()
            ->andReturn($mockCustomer);

        // Mock the createProduct call
        $mockProduct = StripeObject::constructFrom(['id' => 'prod_test_123']);
        $mockStripeClient->shouldReceive('createProduct')
            ->once()
            ->with(['name' => 'Test Booking - Payment'])
            ->andReturn($mockProduct);

        // Mock the createCheckoutSession call
        $mockSession = StripeObject::constructFrom([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_123'
        ]);
        $mockStripeClient->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn($mockSession);

        // Bind the mock to the container
        $this->app->instance(StripeClientInterface::class, $mockStripeClient);

        // Act: Create the checkout session
        $paymentService = app(ContactPaymentService::class);
        $checkoutUrl = $paymentService->createCheckoutSession($booking, $contact, 100.00);

        // Assert: Verify the checkout URL was returned
        $this->assertEquals('https://checkout.stripe.com/pay/cs_test_123', $checkoutUrl);

        // Verify the customer was created in the database
        $this->assertDatabaseHas('stripe_customers', [
            'contact_id' => $contact->id,
            'stripe_customer_id' => 'cus_test_123',
        ]);

        // Verify the product was created in the database
        $this->assertDatabaseHas('stripe_products', [
            'band_id' => $booking->band_id,
            'stripe_product_id' => 'prod_test_123',
            'product_name' => 'Test Booking - Payment',
        ]);
    }

    /**
     * Example test showing how to test error handling with mocked Stripe client
     */
    public function test_create_checkout_session_handles_stripe_errors()
    {
        // Arrange
        $booking = Bookings::factory()->create();
        $contact = Contacts::factory()->create();

        StripeAccounts::create([
            'band_id' => $booking->band_id,
            'stripe_account_id' => 'acct_test_123',
            'status' => 'active',
        ]);

        // Create a mock that throws an exception
        $mockStripeClient = Mockery::mock(StripeClientInterface::class);
        $mockStripeClient->shouldReceive('retrieveAccount')
            ->andThrow(new \Exception('Stripe API error'));

        $this->app->instance(StripeClientInterface::class, $mockStripeClient);

        // Act & Assert: Verify exception is handled
        $paymentService = app(ContactPaymentService::class);

        // This test shows you can verify error handling behavior
        // In a real scenario, you might want to catch and handle the exception
        $this->expectException(\Exception::class);
        $paymentService->createCheckoutSession($booking, $contact, 100.00);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
