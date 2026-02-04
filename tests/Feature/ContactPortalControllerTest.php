<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\EventTypes;
use App\Models\MediaFile;
use App\Models\Payments;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\StripeAccounts;
use App\Models\User;
use App\Services\ContactPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class ContactPortalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create event types that bookings might need
        EventTypes::factory()->count(6)->create();
    }

    /**
     * Test contact can view dashboard
     */
    public function test_contact_can_view_dashboard()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        // Create a booking associated with this contact
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
        ]);
        $booking->contacts()->attach($contact->id, [
            'role' => 'Primary Contact',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                ->has('portal')
                ->has('bookings')
        );
    }

    /**
     * Test dashboard shows bookings from last 6 months
     */
    public function test_dashboard_shows_recent_bookings()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        // Create recent booking
        $recentBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'name' => 'Recent Event',
        ]);
        $recentBooking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Create old booking (should not appear)
        $oldBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->subMonths(7),
            'name' => 'Old Event',
        ]);
        $oldBooking->contacts()->attach($contact->id, ['is_primary' => true]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                ->where('bookings.0.name', 'Recent Event')
                ->where('bookings', fn ($bookings) => count($bookings) === 1)
        );
    }

    /**
     * Test dashboard shows payment information
     */
    public function test_dashboard_shows_payment_information()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2000,
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add a payment
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1000,
            'status' => 'paid',
            'date' => now(),
            'name' => 'Deposit',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                ->where('bookings.0.price', '2000.00') // Price is formatted as string
                ->where('bookings.0.amount_paid', '1000.00') // amount_paid formatted as string
                ->where('bookings.0.amount_due', '1000.00') // amount_due formatted as string
                ->where('bookings.0.has_balance', true)
        );
    }

    /**
     * Test guest cannot access dashboard
     */
    public function test_guest_cannot_access_dashboard()
    {
        $response = $this->get(route('portal.dashboard'));

        // Guests are redirected to login (might be /login not /contact/login depending on middleware config)
        $response->assertRedirect();
    }

    /**
     * Test contact can view payment page for their booking
     */
    public function test_contact_can_view_payment_page_for_their_booking()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2000,
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.booking.payment', $booking));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Payment')
                ->has('booking')
                ->has('portal')
                ->where('booking.id', $booking->id)
        );
    }

    /**
     * Test contact cannot view payment page for another contact's booking
     */
    public function test_contact_cannot_view_payment_page_for_other_booking()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $otherContact = Contacts::factory()->create([
            'band_id' => $band->id,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
        ]);
        $booking->contacts()->attach($otherContact->id, ['is_primary' => true]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.booking.payment', $booking));

        $response->assertStatus(403);
    }

    /**
     * Test contact can create checkout session
     */
    public function test_contact_can_create_checkout_session()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        // Create Stripe account for band
        StripeAccounts::create([
            'band_id' => $band->id,
            'stripe_account_id' => 'acct_test_123456',
            'status' => 'active',
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2000,
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Mock the ContactPaymentService
        $mockService = Mockery::mock(ContactPaymentService::class);
        $mockService->shouldReceive('createCheckoutSession')
            ->once()
            ->with(
                Mockery::type(Bookings::class), 
                Mockery::type(Contacts::class), 
                1000.0, 
                true
            )
            ->andReturn('https://checkout.stripe.com/test-session');

        $this->app->instance(ContactPaymentService::class, $mockService);

        $response = $this->actingAs($contact, 'contact')
            ->postJson(route('portal.booking.checkout', $booking), [
                'amount' => 1000,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'checkout_url' => 'https://checkout.stripe.com/test-session',
        ]);
    }

    /**
     * Test checkout session requires valid amount
     */
    public function test_checkout_session_requires_valid_amount()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2000,
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Test with amount exceeding amount_due
        $response = $this->actingAs($contact, 'contact')
            ->postJson(route('portal.booking.checkout', $booking), [
                'amount' => 3000, // More than price
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test checkout session requires positive amount
     */
    public function test_checkout_session_requires_positive_amount()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2000,
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        $response = $this->actingAs($contact, 'contact')
            ->postJson(route('portal.booking.checkout', $booking), [
                'amount' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /**
     * Test contact cannot create checkout for other contact's booking
     */
    public function test_contact_cannot_create_checkout_for_other_booking()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $otherContact = Contacts::factory()->create([
            'band_id' => $band->id,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2000,
        ]);
        $booking->contacts()->attach($otherContact->id, ['is_primary' => true]);

        $response = $this->actingAs($contact, 'contact')
            ->postJson(route('portal.booking.checkout', $booking), [
                'amount' => 1000,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test contact can view payment history
     */
    public function test_contact_can_view_payment_history()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2000,
            'name' => 'Test Event',
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add payments
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1000,
            'status' => 'paid',
            'date' => now(),
            'name' => 'Deposit',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.payment.history'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/PaymentHistory')
                ->has('payments')
                ->has('contact')
                ->where('payments.0.booking_name', 'Test Event')
                ->where('payments.0.amount', '1000.00') // Amount is formatted as string
        );
    }

    /**
     * Test payment history only shows paid payments
     */
    public function test_payment_history_only_shows_paid_payments()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2000,
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add paid payment
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1000,
            'status' => 'paid',
            'date' => now(),
            'name' => 'Paid Payment',
        ]);

        // Add pending payment
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 500,
            'status' => 'pending',
            'date' => now(),
            'name' => 'Pending Payment',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.payment.history'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/PaymentHistory')
                ->where('payments', fn ($payments) => count($payments) === 1)
        );
    }

    /**
     * Test contact can view payment success page
     */
    public function test_contact_can_view_payment_success_page()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.payment.success', ['session_id' => 'cs_test_123']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/PaymentSuccess')
                ->where('session_id', 'cs_test_123')
        );
    }

    /**
     * Test contact can view payment cancelled page
     */
    public function test_contact_can_view_payment_cancelled_page()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.payment.cancelled'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/PaymentCancelled')
        );
    }

    /**
     * Test dashboard shows correct payment status flags
     */
    public function test_dashboard_shows_correct_payment_flags()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        // Create fully paid booking
        $paidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 1000,
            'name' => 'Paid Event',
        ]);
        $paidBooking->contacts()->attach($contact->id, ['is_primary' => true]);
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $paidBooking->id,
            'band_id' => $band->id,
            'amount' => 1000,
            'status' => 'paid',
            'date' => now(),
            'name' => 'Full Payment',
        ]);

        // Create partially paid booking
        $partialBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(14),
            'price' => 2000,
            'name' => 'Partial Event',
        ]);
        $partialBooking->contacts()->attach($contact->id, ['is_primary' => true]);
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $partialBooking->id,
            'band_id' => $band->id,
            'amount' => 500,
            'status' => 'paid',
            'date' => now(),
            'name' => 'Deposit',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                // Bookings are sorted by date desc, so Partial Event (14 days) comes first
                ->where('bookings.0.name', 'Partial Event')
                ->where('bookings.0.has_balance', true)
                ->where('bookings.1.name', 'Paid Event')
                ->where('bookings.1.has_balance', false)
        );
    }

    /**
     * Test checkout session handles service errors gracefully
     */
    public function test_checkout_session_handles_service_errors()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        StripeAccounts::create([
            'band_id' => $band->id,
            'stripe_account_id' => 'acct_test_123456',
            'status' => 'active',
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2000,
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Mock the ContactPaymentService to throw an exception
        $mockService = Mockery::mock(ContactPaymentService::class);
        $mockService->shouldReceive('createCheckoutSession')
            ->once()
            ->andThrow(new \Exception('Stripe API error'));

        $this->app->instance(ContactPaymentService::class, $mockService);

        $response = $this->actingAs($contact, 'contact')
            ->postJson(route('portal.booking.checkout', $booking), [
                'amount' => 1000,
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'error' => 'Failed to create payment session: Stripe API error',
        ]);
    }

    /**
     * Test payment amounts are correctly formatted in cents (Stripe format)
     * This ensures the Price cast is properly converting cents to dollars
     */
    public function test_payment_amounts_use_stripe_cents_format()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 1500, // $1,500.00 (cast will multiply by 100 to store as cents)
            'name' => 'Test Event',
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add payment in dollars (Price cast will convert to cents for storage)
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 500, // $500.00 (cast will store as 50000 cents)
            'status' => 'paid',
            'date' => now(),
            'name' => 'Deposit',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                ->where('bookings.0.price', '1500.00') // Should be formatted as dollars
                ->where('bookings.0.amount_paid', '500.00') // Should be converted to dollars
                ->where('bookings.0.amount_due', '1000.00') // Should be calculated correctly
        );
    }

    /**
     * Test is_paid flag correctly handles Stripe cents format
     * This prevents showing "Paid" when amount is still due
     */
    public function test_is_paid_flag_correctly_handles_cents_to_dollar_conversion()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        // Booking with price in dollars (cast will multiply by 100)
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 1500, // $1,500.00 (cast will store as 150000 cents)
            'name' => 'Partially Paid Event',
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add partial payment in dollars (Price cast will convert to cents)
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1000, // $1,000.00 (cast will store as 100000 cents - not full amount)
            'status' => 'paid',
            'date' => now(),
            'name' => 'Partial Payment',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                ->where('bookings.0.is_paid', false) // Should NOT be marked as paid
                ->where('bookings.0.has_balance', true) // Should have balance due
                ->where('bookings.0.amount_due', '500.00') // $500 still due
        );
    }

    /**
     * Test is_paid flag is true when booking is fully paid
     */
    public function test_is_paid_flag_true_when_fully_paid()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 1500, // $1,500.00 (cast will store as 150000 cents)
            'name' => 'Fully Paid Event',
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add full payment in dollars (Price cast will convert to cents)
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1500, // $1,500.00 (cast will store as 150000 cents - full amount)
            'status' => 'paid',
            'date' => now(),
            'name' => 'Full Payment',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                ->where('bookings.0.is_paid', true) // Should be marked as paid
                ->where('bookings.0.has_balance', false) // Should have no balance
                ->where('bookings.0.amount_due', '0.00') // No amount due
        );
    }

    /**
     * Test is_paid flag is true when overpaid
     */
    public function test_is_paid_flag_true_when_overpaid()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 1000, // $1,000.00 (cast will store as 100000 cents)
            'name' => 'Overpaid Event',
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add payment that exceeds price (Price cast will convert to cents)
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1200, // $1,200.00 (cast will store as 120000 cents - overpaid)
            'status' => 'paid',
            'date' => now(),
            'name' => 'Overpayment',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                ->where('bookings.0.is_paid', true) // Should be marked as paid
                ->where('bookings.0.has_balance', false) // Negative balance = no balance due
        );
    }

    /**
     * Test payment history shows correct amounts in dollars
     */
    public function test_payment_history_shows_correct_dollar_amounts()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 2500, // $2,500.00 (cast will store as 250000 cents)
            'name' => 'Wedding Event',
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add payment in dollars (Price cast will convert to cents)
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1250, // $1,250.00 (cast will store as 125000 cents)
            'status' => 'paid',
            'date' => now(),
            'name' => 'Deposit Payment',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.payment.history'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/PaymentHistory')
                ->where('payments.0.amount', '1250.00') // Should show as dollars, not cents
        );
    }

    /**
     * Test is_paid only counts payments with 'paid' status
     */
    public function test_is_paid_only_counts_paid_status_payments()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 1000, // $1,000.00 (cast will store as 100000 cents)
            'name' => 'Event with Pending Payments',
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add paid payment in dollars (Price cast will convert to cents)
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 500, // $500.00 (cast will store as 50000 cents)
            'status' => 'paid',
            'date' => now(),
            'name' => 'Paid Payment',
        ]);

        // Add pending payment (should not count toward is_paid)
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 500, // $500.00 (cast will store as 50000 cents)
            'status' => 'pending',
            'date' => now(),
            'name' => 'Pending Payment',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                ->where('bookings.0.is_paid', false) // Only $500 paid of $1000
                ->where('bookings.0.amount_paid', '500.00') // Only counts paid status
                ->where('bookings.0.has_balance', true)
        );
    }

    /**
     * Test multiple payments correctly sum up
     */
    public function test_multiple_payments_sum_correctly()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'price' => 3000, // $3,000.00 (cast will store as 300000 cents)
            'name' => 'Event with Multiple Payments',
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Add multiple payments in dollars (Price cast will convert to cents)
        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1000, // $1,000.00 (cast will store as 100000 cents)
            'status' => 'paid',
            'date' => now()->subDays(30),
            'name' => 'Deposit',
        ]);

        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1000, // $1,000.00 (cast will store as 100000 cents)
            'status' => 'paid',
            'date' => now()->subDays(15),
            'name' => 'Second Payment',
        ]);

        Payments::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'amount' => 1000, // $1,000.00 (cast will store as 100000 cents)
            'status' => 'paid',
            'date' => now(),
            'name' => 'Final Payment',
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/Dashboard')
                ->where('bookings.0.is_paid', true) // $3000 paid = $3000 price
                ->where('bookings.0.amount_paid', '3000.00') // All three payments sum correctly
                ->where('bookings.0.amount_due', '0.00')
                ->where('bookings.0.has_balance', false)
        );
    }

    /**
     * Test contact can view media page
     */
    public function test_contact_can_view_media_page()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.media'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Contact/Media')
                ->has('folders')
                ->has('totalFiles')
        );
    }

    /**
     * Test media page handles rehearsal events without contacts relationship
     * This reproduces the bug: "Call to undefined method App\Models\Rehearsal::contacts()"
     *
     * The bug occurs when a booking's media folder path matches a rehearsal event's media_folder_path,
     * and the code tries to query all events with that path (including rehearsals).
     */
    public function test_media_page_handles_rehearsal_events_without_error()
    {
        $band = Bands::factory()->withOwners()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        // Use a shared folder path that will be used by both booking and rehearsal
        $sharedFolderPath = 'band-' . $band->id . '/shared-media';

        // Create a booking with contact and media
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'date' => now()->addDays(7),
            'enable_portal_media_access' => true,
        ]);
        $booking->contacts()->attach($contact->id, ['is_primary' => true]);

        // Create an event linked to the booking with the shared folder path
        $bookingEvent = \App\Models\Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Booking Event with Media',
            'date' => $booking->date,
            'time' => '19:00:00',
            'media_folder_path' => $sharedFolderPath,  // Same path as rehearsal
            'enable_portal_media_access' => true,
        ]);

        // Create a media file in the shared folder for the booking
        // The folder_path is what links it to the booking event
        // Let the factory create its own user to avoid foreign key issues
        MediaFile::factory()->image()->create([
            'band_id' => $band->id,
            'folder_path' => $sharedFolderPath,
            'filename' => 'test-photo.jpg',
        ]);

        // Create a rehearsal (which does NOT have contacts relationship)
        $rehearsalSchedule = \App\Models\RehearsalSchedule::factory()->create([
            'band_id' => $band->id,
            'name' => 'Weekly Rehearsal',
            'frequency' => 'weekly',
        ]);

        $rehearsal = \App\Models\Rehearsal::factory()->create([
            'band_id' => $band->id,
            'rehearsal_schedule_id' => $rehearsalSchedule->id,
        ]);

        // Create a rehearsal event with the SAME media_folder_path
        // This is what triggers the bug - when querying events by folder_path,
        // both booking and rehearsal events match, and the code tries to call contacts() on Rehearsal
        \App\Models\Events::factory()->create([
            'eventable_type' => \App\Models\Rehearsal::class,
            'eventable_id' => $rehearsal->id,
            'title' => 'Rehearsal Event',
            'date' => now()->addDays(3),
            'time' => '18:00:00',
            'media_folder_path' => $sharedFolderPath,  // Same path as booking!
            'enable_portal_media_access' => true,
        ]);

        // When the contact views the media page, it should not crash
        // The bug manifests when the code tries to find events for the folder path
        // and encounters the rehearsal event without a contacts() relationship
        $response = $this->actingAs($contact, 'contact')
            ->get(route('portal.media'));

        // Should succeed and show the booking's media
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Contact/Media')
                ->has('folders')
                ->where('folders.0.path', $sharedFolderPath)
                ->where('folders.0.file_count', 1)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
