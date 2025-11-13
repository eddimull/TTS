<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FinancesControllerTest extends TestCase
{
    use RefreshDatabase;

    private $band;
    private $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);
    }

    public function test_unpaid_services_displays_correct_amount_paid_for_partial_payments()
    {
        // Create a booking with partial payment (price is in dollars, gets multiplied by 100 for storage)
        $partiallyPaidBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Partially Paid Event',
            'price' => 100, // $100.00
        ]);
        Payments::factory()->create([
            'payable_id' => $partiallyPaidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 30, // $30.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        // Create a booking with no payment
        $unpaidBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Unpaid Event',
            'price' => 50, // $50.00
        ]);

        // Create a fully paid booking (should not appear in unpaid)
        $fullyPaidBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Fully Paid Event',
            'price' => 20, // $20.00
        ]);
        Payments::factory()->create([
            'payable_id' => $fullyPaidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 20, // $20.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->owner)->get(route('Unpaid Services'));

        $response->assertStatus(200);
        $response->assertInertia(fn($assert) => $assert
            ->component('Finances/Unpaid')
            ->has('unpaid', 1)
            ->has('unpaid.0.unpaidBookings', 2)
            ->where('unpaid.0.id', $this->band->id)
        );

        // Get the unpaid bookings to verify values
        $unpaidData = $response->viewData('page')['props']['unpaid'][0]['unpaidBookings'];

        // Find bookings by name since order may vary
        $partialBooking = collect($unpaidData)->firstWhere('name', 'Partially Paid Event');
        $nopaymentBooking = collect($unpaidData)->firstWhere('name', 'Unpaid Event');

        $this->assertEquals('100.00', $partialBooking['price']);
        $this->assertEquals('30.00', $partialBooking['amount_paid']);

        $this->assertEquals('50.00', $nopaymentBooking['price']);
        $this->assertEquals('0.00', $nopaymentBooking['amount_paid']);
    }

    public function test_unpaid_services_shows_correct_totals()
    {
        // Create multiple unpaid bookings with various payment statuses
        $booking1 = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 100, // $100.00
        ]);
        Payments::factory()->create([
            'payable_id' => $booking1->id,
            'payable_type' => Bookings::class,
            'amount' => 30, // $30.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        $booking2 = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 50, // $50.00
        ]);
        Payments::factory()->create([
            'payable_id' => $booking2->id,
            'payable_type' => Bookings::class,
            'amount' => 20, // $20.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        $booking3 = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 80, // $80.00, no payment
        ]);

        // Total price: $230.00
        // Total paid: $50.00
        // Total due: $180.00

        $response = $this->actingAs($this->owner)->get(route('Unpaid Services'));

        $response->assertStatus(200);
        $response->assertInertia(fn($assert) => $assert
            ->component('Finances/Unpaid')
            ->has('unpaid', 1)
            ->has('unpaid.0.unpaidBookings', 3)
        );

        // Verify the data returned for frontend calculations
        $unpaidData = $response->viewData('page')['props']['unpaid'][0]['unpaidBookings'];

        $this->assertCount(3, $unpaidData);

        // Verify that all amounts are correctly formatted
        foreach ($unpaidData as $booking) {
            $this->assertIsString($booking['price']);
            $this->assertIsString($booking['amount_paid']);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $booking['price']);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $booking['amount_paid']);
        }
    }

    public function test_paid_services_displays_correct_amount_paid()
    {
        // Create fully paid bookings
        $paidBooking1 = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Paid Event 1',
            'price' => 100, // $100.00
        ]);
        Payments::factory()->create([
            'payable_id' => $paidBooking1->id,
            'payable_type' => Bookings::class,
            'amount' => 100, // $100.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        $paidBooking2 = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Paid Event 2',
            'price' => 50, // $50.00
        ]);
        // Multiple payments totaling the full amount
        Payments::factory()->create([
            'payable_id' => $paidBooking2->id,
            'payable_type' => Bookings::class,
            'amount' => 30, // $30.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);
        Payments::factory()->create([
            'payable_id' => $paidBooking2->id,
            'payable_type' => Bookings::class,
            'amount' => 20, // $20.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        // Create an unpaid booking (should not appear)
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 30, // $30.00
        ]);

        $response = $this->actingAs($this->owner)->get(route('Paid Services'));

        $response->assertStatus(200);
        $response->assertInertia(fn($assert) => $assert
            ->component('Finances/Paid')
            ->has('paid', 1)
            ->has('paid.0.paidBookings', 2)
        );

        // Get the paid bookings to verify values
        $paidData = $response->viewData('page')['props']['paid'][0]['paidBookings'];

        // Find bookings by name since order may vary
        $paid1 = collect($paidData)->firstWhere('name', 'Paid Event 1');
        $paid2 = collect($paidData)->firstWhere('name', 'Paid Event 2');

        $this->assertEquals('100.00', $paid1['price']);
        $this->assertEquals('100.00', $paid1['amount_paid']);

        $this->assertEquals('50.00', $paid2['price']);
        $this->assertEquals('50.00', $paid2['amount_paid']);
    }

    public function test_unpaid_services_excludes_fully_paid_bookings()
    {
        // Create a fully paid booking
        $fullyPaidBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 100, // $100.00
        ]);
        Payments::factory()->create([
            'payable_id' => $fullyPaidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 100, // $100.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        // Create an overpaid booking (shouldn't appear in unpaid)
        $overpaidBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 50, // $50.00
        ]);
        Payments::factory()->create([
            'payable_id' => $overpaidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 60, // $60.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->owner)->get(route('Unpaid Services'));

        $response->assertStatus(200);
        $response->assertInertia(fn($assert) => $assert
            ->component('Finances/Unpaid')
            ->has('unpaid', 1)
            ->has('unpaid.0.unpaidBookings', 0) // No unpaid bookings
        );
    }

    public function test_unpaid_services_handles_multiple_payments_on_same_booking()
    {
        // Create a booking with multiple partial payments
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Multi-Payment Event',
            'price' => 100, // $100.00
        ]);

        // Three payments totaling $60.00
        Payments::factory()->create([
            'payable_id' => $booking->id,
            'payable_type' => Bookings::class,
            'amount' => 20, // $20.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);
        Payments::factory()->create([
            'payable_id' => $booking->id,
            'payable_type' => Bookings::class,
            'amount' => 25, // $25.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);
        Payments::factory()->create([
            'payable_id' => $booking->id,
            'payable_type' => Bookings::class,
            'amount' => 15, // $15.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->owner)->get(route('Unpaid Services'));

        $response->assertStatus(200);
        $response->assertInertia(fn($assert) => $assert
            ->component('Finances/Unpaid')
            ->has('unpaid', 1)
            ->has('unpaid.0.unpaidBookings', 1)
            ->where('unpaid.0.unpaidBookings.0.name', 'Multi-Payment Event')
            ->where('unpaid.0.unpaidBookings.0.price', '100.00')
            ->where('unpaid.0.unpaidBookings.0.amount_paid', '60.00') // Sum of all payments
        );
    }

    public function test_paid_unpaid_page_returns_correct_data()
    {
        // Create various bookings
        $unpaidBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 100, // $100.00
        ]);

        $paidBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 50, // $50.00
        ]);
        Payments::factory()->create([
            'payable_id' => $paidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 50, // $50.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        $partialBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 80, // $80.00
        ]);
        Payments::factory()->create([
            'payable_id' => $partialBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 30, // $30.00
            'band_id' => $this->band->id,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->owner)->get(route('Paid/Unpaid'));

        $response->assertStatus(200);
        $response->assertInertia(fn($assert) => $assert
            ->component('Finances/PaidUnpaid')
            ->has('allBookings', 1)
            ->has('allBookings.0.paidBookings', 1)
            ->has('allBookings.0.unpaidBookings', 2) // unpaid and partial
            ->where('allBookings.0.paidBookings.0.amount_paid', '50.00')
        );

        // Verify partial payment amount is correct (order may vary)
        $unpaidData = $response->viewData('page')['props']['allBookings'][0]['unpaidBookings'];
        $partialPaymentBooking = collect($unpaidData)->firstWhere('price', '80.00');
        $this->assertNotNull($partialPaymentBooking);
        $this->assertEquals('30.00', $partialPaymentBooking['amount_paid']);
    }
}
