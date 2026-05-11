<?php

namespace Tests\Feature\Pdf;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingContractRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Render the bookingContract.blade.php view with the given booking
     * and a fresh signer, returning the resulting HTML string. The
     * Blade requires a logoDataUri prop, so we stub it with a 1x1 PNG.
     */
    private function renderContract(Bookings $booking, Contacts $signer): string
    {
        return view('pdf.bookingContract', [
            'booking' => $booking->fresh(['band', 'events', 'contacts', 'contract']),
            'logoDataUri' => 'data:image/png;base64,iVBORw0KGgo=',
            'signer' => $signer,
        ])->render();
    }

    private function makeBookingWithContract(array $bookingAttrs = []): Bookings
    {
        $band = Bands::factory()->create([
            'name' => 'Test Band',
            'address' => '1 Test Way',
            'city' => 'Testville',
            'state' => 'LA',
            'zip' => '70001',
        ]);

        $user = User::factory()->create();

        $booking = Bookings::factory()->create(array_merge([
            'band_id' => $band->id,
            'author_id' => $user->id,
            'name' => 'Contract Render Test',
            'price' => 5000,
            'status' => 'pending',
            'contract_option' => 'default',
            'event_type_id' => 2, // anything other than 1 to skip the wedding "SPECIAL INSTRUCTIONS" branch
        ], $bookingAttrs));

        // Bookings::factory does not create a contract automatically — the Blade's
        // `$booking->contract->custom_terms` access requires one.
        if (!$booking->contract) {
            Contracts::factory()->create([
                'contractable_type' => Bookings::class,
                'contractable_id' => $booking->id,
                'author_id' => $user->id,
                'status' => 'pending',
                'custom_terms' => [],
            ]);
            $booking->refresh();
        }

        return $booking;
    }

    private function makeSigner(Bands $band, Bookings $booking): Contacts
    {
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'name' => 'Jane Buyer',
            'email' => 'jane@example.test',
        ]);
        $booking->contacts()->attach($contact, ['is_primary' => true]);
        return $contact;
    }

    public function test_single_event_contract_renders_today_wording(): void
    {
        $booking = $this->makeBookingWithContract();
        $signer = $this->makeSigner($booking->band, $booking);

        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'title' => 'Anniversary Performance',
            'date' => '2026-06-13',
            'start_time' => '19:00',
            'end_time' => '22:00', // 3 hours
            'venue_name' => 'Symphony Hall',
        ]);

        $html = $this->renderContract($booking, $signer);

        $this->assertStringContainsString('Date:</span> 06/13/2026', $html);
        $this->assertStringContainsString('Performance Length:</span> 3 hours', $html);
        $this->assertStringContainsString('Venue:</span> Symphony Hall', $html);

        // Overtime rate for single-event: (5000 / 3) * 1.5 / 1 = 2500.00
        $this->assertStringContainsString('2,500.00', $html);

        // Multi-event markers must NOT appear.
        $this->assertStringNotContainsString('Performances:', $html);
        $this->assertStringNotContainsString('Total Performance Length:', $html);
    }

    public function test_multi_event_contract_renders_performances_list_and_total_duration(): void
    {
        $booking = $this->makeBookingWithContract();
        $signer = $this->makeSigner($booking->band, $booking);

        $eventDates = [
            ['date' => '2026-06-12', 'title' => 'Rehearsal'],
            ['date' => '2026-06-13', 'title' => 'Saturday performance'],
            ['date' => '2026-06-14', 'title' => 'Sunday performance'],
        ];
        foreach ($eventDates as $row) {
            Events::factory()->create([
                'eventable_type' => Bookings::class,
                'eventable_id' => $booking->id,
                'title' => $row['title'],
                'date' => $row['date'],
                'start_time' => '19:00',
                'end_time' => '21:00', // 2 hours each → 6 total
                'venue_name' => 'Symphony Hall',
            ]);
        }

        $html = $this->renderContract($booking, $signer);

        $this->assertStringContainsString('Performances:', $html);
        $this->assertStringContainsString('Total Performance Length:</span> 6 hours', $html);
        $this->assertStringContainsString('Rehearsal', $html);
        $this->assertStringContainsString('Saturday performance', $html);
        $this->assertStringContainsString('Sunday performance', $html);
        // Each event row carries the Blade's date format D n/j/Y.
        $this->assertStringContainsString('6/12/2026', $html);
        $this->assertStringContainsString('6/13/2026', $html);
        $this->assertStringContainsString('6/14/2026', $html);

        // Overtime: 5000 / 6 * 1.5 / 3 = 416.67
        $this->assertStringContainsString('416.67', $html);

        // The single-event "Date:" / "Venue:" labels must NOT appear in
        // the multi-event branch.
        $this->assertStringNotContainsString('Date:</span> 06', $html);
        $this->assertStringNotContainsString('Venue:</span> Symphony Hall', $html);
    }
}
