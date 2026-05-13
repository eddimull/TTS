<?php

namespace Tests\Unit\Models;

use App\Models\Bookings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function existing_bookings_default_to_50_percent_deposit_after_migration(): void
    {
        // BookingsFactory::definition() creates a Bands::factory()->withOwners()
        // for us — no manual band/owner setup needed for this assertion.
        $booking = Bookings::factory()->create(['price' => '1000.00']);

        $this->assertSame('percent', $booking->fresh()->deposit_type);
        $this->assertSame('50.00', (string) $booking->fresh()->deposit_value);
    }
}
