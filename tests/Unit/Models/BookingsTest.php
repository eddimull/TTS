<?php

namespace Tests\Unit\Models;

use App\Models\Bookings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_bookings_default_to_50_percent_deposit_after_migration(): void
    {
        // BookingsFactory::definition() creates a Bands::factory()->withOwners()
        // for us — no manual band/owner setup needed for this assertion.
        $booking = Bookings::factory()->create(['price' => '1000.00']);

        $this->assertSame('percent', $booking->fresh()->deposit_type);
        $this->assertSame('50.00', (string) $booking->fresh()->deposit_value);
    }

    public function test_expected_deposit_amount_uses_percent_mode(): void
    {
        $booking = Bookings::factory()->create([
            'price'         => '1000.00',
            'deposit_type'  => 'percent',
            'deposit_value' => '25.00',
        ]);
        $this->assertSame('250.00', $booking->expected_deposit_amount);
    }

    public function test_expected_deposit_amount_uses_amount_mode(): void
    {
        $booking = Bookings::factory()->create([
            'price'         => '1000.00',
            'deposit_type'  => 'amount',
            'deposit_value' => '300.00',
        ]);
        $this->assertSame('300.00', $booking->expected_deposit_amount);
    }

    public function test_expected_deposit_amount_returns_zero_when_price_is_null(): void
    {
        $booking = Bookings::factory()->create([
            'price'         => null,
            'deposit_type'  => 'percent',
            'deposit_value' => '50.00',
        ]);
        $this->assertSame('0.00', $booking->expected_deposit_amount);
    }

    public function test_legacy_50_percent_default_produces_same_number_as_before(): void
    {
        $booking = Bookings::factory()->create([
            'price'         => '800.00',
            'deposit_type'  => 'percent',
            'deposit_value' => '50.00',
        ]);
        $this->assertSame('400.00', $booking->expected_deposit_amount);
    }
}
