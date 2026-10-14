<?php

namespace Tests\Feature\Pdf;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingContractSignersRenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(?string $override = null): Bookings
    {
        $band = Bands::factory()->create([
            'name' => 'Test Band', 'address' => '1 Test Way', 'city' => 'Testville', 'state' => 'LA', 'zip' => '70001',
        ]);
        $user = User::factory()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'author_id' => $user->id,
            'contract_option' => 'default',
            'event_type_id' => 2,
        ]);
        Contracts::factory()->create([
            'contractable_type' => Bookings::class,
            'contractable_id' => $booking->id,
            'author_id' => $user->id,
            'status' => 'pending',
            'custom_terms' => [],
            'buyer_name_override' => $override,
        ]);

        return $booking;
    }

    private function signer(Bookings $booking, string $name): Contacts
    {
        $contact = Contacts::factory()->create(['band_id' => $booking->band_id, 'name' => $name]);
        $booking->contacts()->attach($contact);

        return $contact;
    }

    private function render(Bookings $booking, array $viewData): string
    {
        return view('pdf.bookingContract', array_merge([
            'booking' => $booking->fresh(['band', 'contract', 'contacts', 'events']),
            'logoDataUri' => '',
        ], $viewData))->render();
    }

    public function test_one_signature_tag_per_signer_matching_pandadoc_roles(): void
    {
        $booking = $this->makeBooking();
        $a = $this->signer($booking, 'Alice Able');
        $b = $this->signer($booking, 'Bob Baker');
        $c = $this->signer($booking, 'Cara Cole');

        $html = $this->render($booking, ['signers' => collect([$a, $b, $c])]);

        $this->assertSame(3, substr_count($html, '{signature:'));
        foreach (['signer1', 'signer2', 'signer3'] as $role) {
            $this->assertSame(1, substr_count($html, "{signature:{$role}_"), "missing tag for {$role}");
        }
        $this->assertStringNotContainsString('{signature:user', $html);
        $this->assertStringContainsString('with <strong>Alice Able, Bob Baker and Cara Cole</strong>', $html);
        $this->assertStringContainsString('<strong class="underline">Bob Baker</strong>', $html);
        $this->assertStringNotContainsString('on behalf of', $html);
    }

    public function test_with_override_every_signer_signs_on_behalf_of_the_buyer(): void
    {
        $booking = $this->makeBooking('The City of Scott');
        $a = $this->signer($booking, 'Mayor Jane Doe');
        $b = $this->signer($booking, 'Clerk John Roe');

        $html = $this->render($booking, ['signers' => collect([$a, $b])]);

        $this->assertStringContainsString('with <strong>The City of Scott</strong>', $html);
        $this->assertSame(2, substr_count($html, 'on behalf of The City of Scott'));
        $this->assertMatchesRegularExpression('/By: <strong>Mayor Jane Doe<\/strong>, on behalf of The City of Scott/', $html);
        $this->assertMatchesRegularExpression('/By: <strong>Clerk John Roe<\/strong>, on behalf of The City of Scott/', $html);
        $this->assertSame(2, substr_count($html, '{signature:'));
    }

    public function test_single_signer_renders_one_block(): void
    {
        $booking = $this->makeBooking();
        $a = $this->signer($booking, 'Alice Able');

        $html = $this->render($booking, ['signers' => collect([$a])]);

        $this->assertSame(1, substr_count($html, '{signature:signer1___'));
        $this->assertSame(1, substr_count($html, '{signature:'));
        $this->assertStringContainsString('with <strong>Alice Able</strong>', $html);
    }

    public function test_legacy_single_signer_view_variable_still_renders(): void
    {
        $booking = $this->makeBooking();
        $a = $this->signer($booking, 'Alice Able');

        $html = $this->render($booking, ['signer' => $a]);

        $this->assertSame(1, substr_count($html, '{signature:signer1___'));
        $this->assertStringContainsString('with <strong>Alice Able</strong>', $html);
    }
}
