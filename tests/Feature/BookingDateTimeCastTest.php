<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventTypes;
use App\Models\userPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingDateTimeCastTest extends TestCase
{
    use RefreshDatabase;

    private $band;
    private $user;
    private $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();
        $this->user = User::factory()->create();
        $this->eventType = EventTypes::factory()->create();

        $this->band->owners()->create(['user_id' => $this->user->id]);

        userPermissions::create([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'read_bookings' => true,
            'write_bookings' => true,
        ]);
    }

    public function test_booking_creation_with_same_day_times()
    {
        $bookingData = [
            'name' => 'Same Day Booking',
            'event_type_id' => $this->eventType->id,
            'date' => '2025-08-20',
            'start_time' => '14:00',
            'duration' => 4,
            'price' => 500,
            'venue_name' => 'Test Venue',
            'contract_option' => 'default',
            'status' => 'confirmed',
        ];

        $response = $this->actingAs($this->user)->post(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(302);

        $booking = Bookings::where('name', 'Same Day Booking')->first();
        $this->assertNotNull($booking);

        // Both start and end times should be on the same day
        $this->assertEquals('2025-08-20 14:00:00', $booking->start_date_time->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-08-20 18:00:00', $booking->end_date_time->format('Y-m-d H:i:s'));
    }

    public function test_booking_creation_spanning_midnight()
    {
        $bookingData = [
            'name' => 'Midnight Spanning Booking',
            'event_type_id' => $this->eventType->id,
            'date' => '2025-08-20',
            'start_time' => '23:00',
            'duration' => 4,
            'price' => 800,
            'venue_name' => 'Night Venue',
            'contract_option' => 'default',
            'status' => 'confirmed',
        ];

        $response = $this->actingAs($this->user)->post(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(302);

        $booking = Bookings::where('name', 'Midnight Spanning Booking')->first();
        $this->assertNotNull($booking);

        // Start time should be on the booking date, end time should be next day
        $this->assertEquals('2025-08-20 23:00:00', $booking->start_date_time->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-08-21 03:00:00', $booking->end_date_time->format('Y-m-d H:i:s'));
    }

    public function test_booking_creation_ending_at_midnight()
    {
        $bookingData = [
            'name' => 'Midnight End Booking',
            'event_type_id' => $this->eventType->id,
            'date' => '2025-08-20',
            'start_time' => '22:00',
            'duration' => 2,
            'price' => 600,
            'venue_name' => 'Evening Venue',
            'contract_option' => 'default',
            'status' => 'confirmed',
        ];

        $response = $this->actingAs($this->user)->post(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(302);

        $booking = Bookings::where('name', 'Midnight End Booking')->first();
        $this->assertNotNull($booking);

        // Start time on booking date, end time at midnight next day
        $this->assertEquals('2025-08-20 22:00:00', $booking->start_date_time->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-08-21 00:00:00', $booking->end_date_time->format('Y-m-d H:i:s'));
    }

    public function test_booking_update_to_span_midnight()
    {
        // Create a booking that doesn't span midnight
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-08-20',
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
        ]);

        // Update it to span midnight
        $updateData = [
            'name' => $booking->name,
            'event_type_id' => $this->eventType->id,
            'date' => '2025-08-20',
            'start_time' => '23:30',
            'end_time' => '01:30',
            'price' => 750,
            'venue_name' => 'Updated Venue',
            'contract_option' => 'default',
            'status' => 'confirmed',
        ];

        $response = $this->actingAs($this->user)->put(route('bands.booking.update', [$this->band, $booking]), $updateData);

        $response->assertStatus(302);

        $booking->refresh();

        // Verify the cast correctly handles the midnight spanning
        $this->assertEquals('2025-08-20 23:30:00', $booking->start_date_time->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-08-21 01:30:00', $booking->end_date_time->format('Y-m-d H:i:s'));
    }

    public function test_booking_creation_with_very_early_end_time()
    {
        $bookingData = [
            'name' => 'Very Early End Booking',
            'event_type_id' => $this->eventType->id,
            'date' => '2025-08-20',
            'start_time' => '23:45',
            'duration' => 1, // This will create end_time of 00:45
            'price' => 400,
            'venue_name' => 'Late Night Venue',
            'contract_option' => 'default',
            'status' => 'confirmed',
        ];

        $response = $this->actingAs($this->user)->post(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(302);

        $booking = Bookings::where('name', 'Very Early End Booking')->first();
        $this->assertNotNull($booking);

        // Start time late on booking date, end time early next day
        $this->assertEquals('2025-08-20 23:45:00', $booking->start_date_time->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-08-21 00:45:00', $booking->end_date_time->format('Y-m-d H:i:s'));
    }

    public function test_booking_with_equal_start_and_end_times()
    {
        // Create a booking directly with equal times to test edge case
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => '2025-08-20',
            'start_time' => '14:30:00',
            'end_time' => '14:30:00',
        ]);

        // Both times should be on the same day when equal
        $this->assertEquals('2025-08-20 14:30:00', $booking->start_date_time->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-08-20 14:30:00', $booking->end_date_time->format('Y-m-d H:i:s'));
    }
}