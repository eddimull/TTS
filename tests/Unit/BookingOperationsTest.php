<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Payments;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingOperationsTest extends TestCase
{
    use RefreshDatabase;


    protected function setUp(): void
    {
        parent::setUp();

        // Check if the database is actually empty
        $bookingsCount = DB::table('bookings')->count();
        $contactsCount = DB::table('contacts')->count();

        if ($bookingsCount > 0 || $contactsCount > 0)
        {
            $this->fail("Database is not empty. Bookings: $bookingsCount, Contacts: $contactsCount");
        }
    }

    public function test_can_create_a_booking()
    {
        $booking = Bookings::factory()->create();

        $this->assertInstanceOf(Bookings::class, $booking);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }


    public function test_belongs_to_a_band()
    {
        $booking = Bookings::factory()->create();

        $this->assertInstanceOf(Bands::class, $booking->band);
    }


    public function test_can_have_multiple_contacts()
    {
        $booking = Bookings::factory()->create();

        $this->assertCount(0, $booking->fresh()->contacts, 'Booking should have no contacts after creation with withoutContacts()');

        $contacts = Contacts::factory()->count(3)->create();

        $booking->contacts()->attach($contacts, ['role' => 'Test Role']);

        $this->assertCount(3, $booking->fresh()->contacts, 'Booking should have exactly 3 contacts after attaching');
    }


    public function test_can_have_a_primary_contact()
    {
        $booking = Bookings::factory()->create();
        $primaryContact = Contacts::factory()->create();
        $secondaryContact = Contacts::factory()->create();

        $booking->contacts()->attach([
            $primaryContact->id => ['role' => 'Primary', 'is_primary' => true],
            $secondaryContact->id => ['role' => 'Secondary', 'is_primary' => false],
        ]);

        $this->assertCount(1, $booking->primaryContact);
        $this->assertEquals($primaryContact->id, $booking->primaryContact->first()->id);
    }


    public function test_creates_booking_with_contacts_using_factory()
    {
        $booking = Bookings::factory()->withContacts()->create();

        $this->assertNotEmpty($booking->contacts);
        $this->assertCount(1, $booking->contacts->where('pivot.is_primary', true));
    }


    public function test_can_scope_to_confirmed_bookings()
    {
        Bookings::factory()->count(2)->confirmed()->create();

        $confirmedBookings = Bookings::where('status', 'confirmed')->get();

        $this->assertCount(2, $confirmedBookings);
    }


    public function test_can_calculate_duration()
    {
        $booking = Bookings::factory()->create([
            'start_time' => '19:00:00',
            'end_time' => '23:00:00',
        ]);

        $this->assertEquals(4, $booking->duration);
    }

    public function test_can_determine_if_booking_is_paid()
    {
        // Create a booking with a price of 1000
        $booking = Bookings::factory()->create([
            'price' => 100000
        ]);

        // Initially, the booking should not be paid
        $this->assertFalse($booking->is_paid);

        // Add a payment of 500
        $booking->payments()->create([
            'name' => 'Test Payment',
            'date' => now(),
            'band_id' => $booking->band_id,
            'amount' => 500
        ]);

        // Refresh the booking model to recalculate the is_paid attribute
        $booking->refresh();

        // The booking should still not be fully paid
        $this->assertFalse($booking->is_paid);

        // Add another payment of 500
        $booking->payments()->create([
            'name' => 'Test Payment',
            'date' => now(),
            'band_id' => $booking->band_id,
            'amount' => 500
        ]);

        // Refresh the booking model again
        $booking->refresh();

        // Now the booking should be fully paid
        $this->assertTrue($booking->is_paid);

        // Add an extra payment of 100
        $booking->payments()->create([
            'name' => 'Test Payment',
            'date' => now(),
            'band_id' => $booking->band_id,
            'amount' => 100
        ]);

        // Refresh the booking model once more
        $booking->refresh();

        // The booking should still be considered paid
        $this->assertTrue($booking->is_paid);
    }
}
