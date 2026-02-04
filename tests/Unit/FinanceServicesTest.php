<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FinanceServices;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Proposals;
use App\Models\Payments;
use App\Models\ProposalPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class FinanceServicesTest extends TestCase
{
    use RefreshDatabase;

    protected $financeServices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->financeServices = new FinanceServices();
    }

    public function testGetBandFinances()
    {
        $band = Bands::factory()->create();
        $unpaidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 10000]);
        $paidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 10000]);

        $result = $this->financeServices->getBandFinances([$band]);

        $this->assertCount(1, $result);
    }

    public function testGetUnpaid()
    {
        $band = Bands::factory()->create();
        $unpaidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 10000]);
        $paidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 10000]);
        Payments::factory()->create(['payable_id' => $paidBooking->id, 'payable_type' => Bookings::class, 'amount' => 10000]);

        $result = $this->financeServices->getUnpaid([$band]);

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]->unpaidBookings);
        $this->assertEquals($unpaidBooking->id, $result[0]->unpaidBookings[0]->id);
    }

    public function testGetUnpaidWithPartialPayment()
    {
        $band = Bands::factory()->create();

        // Create a booking with partial payment
        $partiallyPaidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 10000]);
        Payments::factory()->create([
            'payable_id' => $partiallyPaidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 3000,
            'band_id' => $band->id,
        ]);

        // Create a booking with no payment
        $unpaidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 5000]);

        // Create a fully paid booking (should not appear)
        $fullyPaidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 2000]);
        Payments::factory()->create([
            'payable_id' => $fullyPaidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 2000,
            'band_id' => $band->id,
        ]);

        $result = $this->financeServices->getUnpaid([$band]);

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]->unpaidBookings);

        // Find the partially paid booking in results
        $partialBooking = $result[0]->unpaidBookings->firstWhere('id', $partiallyPaidBooking->id);
        $this->assertNotNull($partialBooking, 'Partially paid booking should be in unpaid results');
        $this->assertEquals(3000, $partialBooking->amount_paid, 'Amount paid should be 3000, not 0');

        // Find the unpaid booking in results
        $zeroPaymentBooking = $result[0]->unpaidBookings->firstWhere('id', $unpaidBooking->id);
        $this->assertNotNull($zeroPaymentBooking, 'Unpaid booking should be in unpaid results');
        $this->assertEquals(0, $zeroPaymentBooking->amount_paid, 'Amount paid should be 0');
    }

    public function testGetPaid()
    {
        $band = Bands::factory()->create();
        $unpaidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 10000]);
        $paidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 10000]);
        Payments::factory()->create(['payable_id' => $paidBooking->id, 'payable_type' => Bookings::class, 'amount' => 10000]);

        $result = $this->financeServices->getPaid([$band]);

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]->paidBookings);
        $this->assertEquals($paidBooking->id, $result[0]->paidBookings[0]->id);
    }

    public function testGetPaidUnpaid()
    {
        $band = Bands::factory()->create();
        $unpaidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 10000]);
        $paidBooking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 10000]);
        Payments::factory()->create(['payable_id' => $paidBooking->id, 'payable_type' => Bookings::class, 'amount' => 10000]);

        $result = $this->financeServices->getPaidUnpaid([$band]);

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]->paidBookings);
        $this->assertCount(1, $result[0]->unpaidBookings);
    }

    public function testGetBandRevenueByYear()
    {
        $band = Bands::factory()->create();
        Payments::factory()->create([
            'band_id' => $band->id,
            'amount' => 10000,
            'date' => '2023-01-01'
        ]);
        Payments::factory()->create([
            'band_id' => $band->id,
            'amount' => 20000,
            'date' => '2023-06-01'
        ]);
        Payments::factory()->create([
            'band_id' => $band->id,
            'amount' => 30000,
            'date' => '2024-01-01'
        ]);

        $result = $this->financeServices->getBandRevenueByYear($band);


        $this->assertCount(2, $result->payments);
        $this->assertEquals(3000000, $result->payments[0]->total);
        $this->assertEquals(3000000, $result->payments[1]->total);
    }

    public function testGetBandPayments()
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        Payments::factory()->count(3)->create([
            'band_id' => $band->id,
            'payable_id' => $booking->id,
            'payable_type' => Bookings::class
        ]);

        $result = $this->financeServices->getBandPayments([$band]);

        $this->assertCount(1, $result);
        $this->assertCount(3, $result[0]->payments);
    }

    public function testGetPaidUnpaidWithSnapshotDate()
    {
        $band = Bands::factory()->create();

        // Create bookings at different times
        $oldUnpaidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);

        $oldPaidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);
        Payments::factory()->create([
            'payable_id' => $oldPaidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 10000
        ]);

        $newUnpaidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(2)
        ]);

        $newPaidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(2)
        ]);
        Payments::factory()->create([
            'payable_id' => $newPaidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 10000
        ]);

        // Test with snapshot date 5 days ago (should only include old bookings)
        $snapshotDate = now()->subDays(5);
        $result = $this->financeServices->getPaidUnpaid([$band], $snapshotDate);

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]->paidBookings);
        $this->assertCount(1, $result[0]->unpaidBookings);
        $this->assertEquals($oldPaidBooking->id, $result[0]->paidBookings[0]->id);
        $this->assertEquals($oldUnpaidBooking->id, $result[0]->unpaidBookings[0]->id);
    }

    public function testGetPaidUnpaidWithoutSnapshotDateReturnsAllBookings()
    {
        $band = Bands::factory()->create();

        $oldUnpaidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);

        $oldPaidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);
        Payments::factory()->create([
            'payable_id' => $oldPaidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 10000
        ]);

        $newUnpaidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(2)
        ]);

        // Test without snapshot date (should include all bookings)
        $result = $this->financeServices->getPaidUnpaid([$band]);

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]->paidBookings);
        $this->assertCount(2, $result[0]->unpaidBookings);
    }

    public function testGetPaidUnpaidWithSnapshotDateBeforeAllBookings()
    {
        $band = Bands::factory()->create();

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(5)
        ]);

        // Test with snapshot date before any bookings were created
        $snapshotDate = now()->subDays(10);
        $result = $this->financeServices->getPaidUnpaid([$band], $snapshotDate);

        $this->assertCount(1, $result);
        $this->assertCount(0, $result[0]->paidBookings);
        $this->assertCount(0, $result[0]->unpaidBookings);
    }

    public function testGetPaidUnpaidWithSnapshotDateAfterAllBookings()
    {
        $band = Bands::factory()->create();

        $unpaidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);

        $paidBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);
        Payments::factory()->create([
            'payable_id' => $paidBooking->id,
            'payable_type' => Bookings::class,
            'amount' => 10000
        ]);

        // Test with snapshot date after all bookings (should include all)
        $snapshotDate = now()->addDays(1);
        $result = $this->financeServices->getPaidUnpaid([$band], $snapshotDate);

        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]->paidBookings);
        $this->assertCount(1, $result[0]->unpaidBookings);
    }
}
