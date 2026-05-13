<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingContractPdfTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function rendered_contract_view_uses_configurable_deposit(): void
    {
        $owner = User::factory()->create();
        $band  = Bands::factory()->create([
            'address'  => '123 Main',
            'city'     => 'New Orleans',
            'state'    => 'LA',
            'zip'      => '70112',
        ]);
        $band->owners()->create(['user_id' => $owner->id]);
        $booking = Bookings::factory()->create([
            'band_id'       => $band->id,
            'price'         => '1000.00',
            'deposit_type'  => 'amount',
            'deposit_value' => '250.00',
        ]);
        Contracts::factory()->create([
            'contractable_type' => Bookings::class,
            'contractable_id'   => $booking->id,
        ]);

        $rendered = view('pdf.bookingContract', [
            'booking'     => $booking->fresh(),
            'logoDataUri' => 'data:image/png;base64,',
            'signer'      => $owner,
        ])->render();

        $this->assertStringContainsString('$250.00', $rendered);
        // Remaining balance = price - deposit = 1000 - 250 = 750
        $this->assertStringContainsString('$750.00', $rendered);
        // Should NOT print the old 50% number anymore for these inputs
        $this->assertStringNotContainsString('$500.00', $rendered);
    }

    /** @test */
    public function rendered_contract_view_uses_percent_mode_correctly(): void
    {
        $owner = User::factory()->create();
        $band  = Bands::factory()->create([
            'address'  => '123 Main', 'city' => 'NOLA', 'state' => 'LA', 'zip' => '70112',
        ]);
        $band->owners()->create(['user_id' => $owner->id]);
        $booking = Bookings::factory()->create([
            'band_id'       => $band->id,
            'price'         => '2000.00',
            'deposit_type'  => 'percent',
            'deposit_value' => '25.00',
        ]);
        Contracts::factory()->create([
            'contractable_type' => Bookings::class,
            'contractable_id'   => $booking->id,
        ]);

        $rendered = view('pdf.bookingContract', [
            'booking'     => $booking->fresh(),
            'logoDataUri' => 'data:image/png;base64,',
            'signer'      => $owner,
        ])->render();

        $this->assertStringContainsString('$500.00', $rendered);   // 25% of 2000
        $this->assertStringContainsString('$1,500.00', $rendered); // remainder
    }
}
