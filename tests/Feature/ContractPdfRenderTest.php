<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPdfRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a realistic Bands + Bookings + Contacts + Contracts graph and
     * render the bookingContract.blade.php view directly to HTML.
     *
     * Mirrors tests/Feature/Pdf/BookingContractRenderTest.php conventions.
     */
    private function renderContract(?string $override, string $signerName): string
    {
        $band = Bands::factory()->create([
            'name' => 'Test Band',
            'address' => '1 Test Way',
            'city' => 'Testville',
            'state' => 'LA',
            'zip' => '70001',
        ]);

        $user = User::factory()->create();

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'author_id' => $user->id,
            'name' => 'Contract Render Test',
            'price' => 5000,
            'status' => 'pending',
            'contract_option' => 'default',
            'event_type_id' => 2, // skip the wedding "SPECIAL INSTRUCTIONS" branch
        ]);

        // Bookings::factory does not create a contract automatically — the
        // Blade accesses $booking->contract->custom_terms and the override.
        if (!$booking->contract) {
            Contracts::factory()->create([
                'contractable_type' => Bookings::class,
                'contractable_id' => $booking->id,
                'author_id' => $user->id,
                'status' => 'pending',
                'custom_terms' => [],
                'buyer_name_override' => $override,
            ]);
        } else {
            $booking->contract->update(['buyer_name_override' => $override]);
        }

        $signer = Contacts::factory()->create([
            'band_id' => $band->id,
            'name' => $signerName,
            'email' => 'signer@example.test',
        ]);
        $booking->contacts()->attach($signer, ['is_primary' => true]);

        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Anniversary Performance',
            'date' => '2026-06-13',
            'start_time' => '19:00',
            'end_time' => '22:00',
            'venue_name' => 'Symphony Hall',
        ]);

        return view('pdf.bookingContract', [
            'booking' => $booking->load('band', 'contract', 'contacts', 'events'),
            'logoDataUri' => '',
            'signer' => $signer,
        ])->render();
    }

    public function test_uses_signer_name_as_buyer_when_no_override(): void
    {
        $html = $this->renderContract(null, 'Mayor Jane Doe');

        $this->assertStringContainsString('with <strong>Mayor Jane Doe</strong>', $html);
        $this->assertStringNotContainsString('on behalf of', $html);
    }

    public function test_uses_override_as_buyer_and_signer_signs_on_behalf(): void
    {
        $html = $this->renderContract('The City of Scott', 'Mayor Jane Doe');

        $this->assertStringContainsString('with <strong>The City of Scott</strong>', $html);
        $this->assertStringContainsString('on behalf of The City of Scott', $html);
        $this->assertStringContainsString('Mayor Jane Doe', $html);
    }
}
