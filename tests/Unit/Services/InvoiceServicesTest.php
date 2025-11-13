<?php

namespace Tests\Unit\Services;

use App\Contracts\StripeClientInterface;
use App\Models\Bookings;
use App\Models\Bands;
use App\Models\Contacts;
use App\Models\StripeAccounts;
use App\Models\User;
use App\Services\InvoiceServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Stripe\StripeObject;
use Tests\TestCase;

class InvoiceServicesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Example test showing how to mock Stripe client for invoice creation
     */
    public function test_create_invoice_with_mocked_stripe_client()
    {
        // Arrange: Create necessary test data
        $user = User::factory()->create();
        Auth::login($user);

        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'name' => 'Test Event',
            'band_id' => $band->id,
        ]);

        $contact = Contacts::factory()->create([
            'email' => 'client@example.com',
            'name' => 'Test Client',
        ]);

        $stripeAccount = StripeAccounts::create([
            'band_id' => $band->id,
            'stripe_account_id' => 'acct_connected_123',
            'status' => 'active',
        ]);

        // Ensure booking has stripe account relation
        $booking->band->setRelation('stripe_accounts', $stripeAccount);

        // Create a mock Stripe client
        $mockStripeClient = Mockery::mock(StripeClientInterface::class);

        // Mock createCustomer
        $mockCustomer = StripeObject::constructFrom(['id' => 'cus_test_456']);
        $mockStripeClient->shouldReceive('createCustomer')
            ->once()
            ->andReturn($mockCustomer);

        // Mock searchProducts (returns no results)
        $mockSearchResult = StripeObject::constructFrom(['data' => []]);
        $mockStripeClient->shouldReceive('searchProducts')
            ->once()
            ->andReturn($mockSearchResult);

        // Mock createProduct (since search returned nothing)
        $mockProduct = StripeObject::constructFrom(['id' => 'prod_test_789']);
        $mockStripeClient->shouldReceive('createProduct')
            ->once()
            ->andReturn($mockProduct);

        // Mock createInvoice
        $mockInvoice = StripeObject::constructFrom(['id' => 'in_test_101112']);
        $mockStripeClient->shouldReceive('createInvoice')
            ->once()
            ->andReturn($mockInvoice);

        // Mock createInvoiceItem
        $mockInvoiceItem = StripeObject::constructFrom(['id' => 'ii_test_131415']);
        $mockStripeClient->shouldReceive('createInvoiceItem')
            ->once()
            ->andReturn($mockInvoiceItem);

        // Mock sendInvoice
        $mockSentInvoice = StripeObject::constructFrom([
            'id' => 'in_test_101112',
            'status' => 'open',
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/acct_test/invst_test_101112'
        ]);
        $mockStripeClient->shouldReceive('sendInvoice')
            ->once()
            ->with('in_test_101112', [])
            ->andReturn($mockSentInvoice);

        // Bind the mock to the container
        $this->app->instance(StripeClientInterface::class, $mockStripeClient);

        // Act: Create the invoice
        $invoiceService = app(InvoiceServices::class);
        $invoiceService->createInvoice($booking, 500.00, $contact->id, true);

        // Assert: Verify database records were created
        $this->assertDatabaseHas('stripe_customers', [
            'contact_id' => $contact->id,
            'stripe_customer_id' => 'cus_test_456',
        ]);

        $this->assertDatabaseHas('stripe_products', [
            'band_id' => $band->id,
            'stripe_product_id' => 'prod_test_789',
            'product_name' => 'Test Event',
        ]);

        $this->assertDatabaseHas('invoices', [
            'booking_id' => $booking->id,
            'stripe_id' => 'in_test_101112',
            'status' => 'open',
            'stripe_url' => 'https://invoice.stripe.com/i/acct_test/invst_test_101112',
            'convenience_fee' => true,
        ]);

        $this->assertDatabaseHas('payments', [
            'band_id' => $booking->band_id,
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_type' => 'invoice',
        ]);
    }

    /**
     * Example test showing how to test product search returning existing product
     */
    public function test_create_invoice_reuses_existing_product()
    {
        // Arrange
        $user = User::factory()->create();
        Auth::login($user);

        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'name' => 'Test Event',
            'band_id' => $band->id,
        ]);

        $contact = Contacts::factory()->create();

        $stripeAccount = StripeAccounts::create([
            'band_id' => $band->id,
            'stripe_account_id' => 'acct_connected_123',
            'status' => 'active',
        ]);

        $booking->band->setRelation('stripe_accounts', $stripeAccount);

        // Create mock Stripe client
        $mockStripeClient = Mockery::mock(StripeClientInterface::class);

        // Mock createCustomer
        $mockCustomer = StripeObject::constructFrom(['id' => 'cus_existing']);
        $mockStripeClient->shouldReceive('createCustomer')->andReturn($mockCustomer);

        // Mock searchProducts returning existing product
        $existingProduct = StripeObject::constructFrom(['id' => 'prod_existing_123']);
        $mockSearchResult = StripeObject::constructFrom([
            'data' => [$existingProduct]
        ]);
        $mockStripeClient->shouldReceive('searchProducts')
            ->once()
            ->andReturn($mockSearchResult);

        // createProduct should NOT be called since product exists
        $mockStripeClient->shouldNotReceive('createProduct');

        // Mock other required calls
        $mockInvoice = StripeObject::constructFrom(['id' => 'in_test_existing']);
        $mockStripeClient->shouldReceive('createInvoice')->andReturn($mockInvoice);

        $mockInvoiceItem = StripeObject::constructFrom(['id' => 'ii_test_existing']);
        $mockStripeClient->shouldReceive('createInvoiceItem')->andReturn($mockInvoiceItem);

        $mockSentInvoice = StripeObject::constructFrom([
            'id' => 'in_test_existing',
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/acct_test/invst_test_existing'
        ]);
        $mockStripeClient->shouldReceive('sendInvoice')->andReturn($mockSentInvoice);

        $this->app->instance(StripeClientInterface::class, $mockStripeClient);

        // Act
        $invoiceService = app(InvoiceServices::class);
        $invoiceService->createInvoice($booking, 500.00, $contact->id, false);

        // Assert: Verify existing product ID was used
        $this->assertDatabaseHas('invoices', [
            'booking_id' => $booking->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
