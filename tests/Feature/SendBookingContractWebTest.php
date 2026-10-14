<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsContractSendFixtures;
use Tests\TestCase;

class SendBookingContractWebTest extends TestCase
{
    use RefreshDatabase;
    use BuildsContractSendFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpContractSending();
    }

    private function send(User $user, Bands $band, Bookings $booking, array $payload): TestResponse
    {
        return $this->actingAs($user)
            ->from("/bands/{$band->id}/booking/{$booking->id}/contract")
            ->post(route('Send Booking Contract', ['band' => $band, 'booking' => $booking]), $payload);
    }

    public function test_multiple_signers_are_sent_with_distinct_roles_and_ccs(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $bride = $this->attachContact($booking, 'Bride Person', true);
        $groom = $this->attachContact($booking, 'Groom Person');
        $planner = $this->attachContact($booking, 'Planner Person');

        $this->send($user, $band, $booking, [
            'signers' => [$bride->id, $groom->id],
            'cc'      => [$planner->id],
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('successMessage');

        $recipients = $this->pandaDocRecipients();
        $this->assertCount(3, $recipients);
        $this->assertSame('signer1', $recipients->firstWhere('email', $bride->email)['role']);
        $this->assertSame('signer2', $recipients->firstWhere('email', $groom->email)['role']);
        $this->assertSame('CC', $recipients->firstWhere('email', $planner->email)['recipient_type']);

        $html = $this->renderedContractHtml[0];
        $this->assertSame(2, substr_count($html, '{signature:'));
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_legacy_signer_payload_still_works(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $signer = $this->attachContact($booking, 'Solo Signer', true);
        $cc = $this->attachContact($booking, 'Copied Person');

        $this->send($user, $band, $booking, [
            'signer' => $signer->id,
            'cc'     => [$cc->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $recipients = $this->pandaDocRecipients();
        $this->assertCount(2, $recipients);
        $this->assertSame('signer1', $recipients->firstWhere('email', $signer->email)['role']);
        $this->assertSame(1, substr_count($this->renderedContractHtml[0], '{signature:'));
    }

    public function test_contact_from_another_booking_is_rejected(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $signer = $this->attachContact($booking, 'Real Signer', true);
        $stranger = Contacts::factory()->create(['band_id' => $band->id]);

        $this->send($user, $band, $booking, ['signers' => [$signer->id, $stranger->id]])
            ->assertRedirect()->assertSessionHasErrors(['signers.1']);

        $this->send($user, $band, $booking, ['signers' => [$signer->id], 'cc' => [$stranger->id]])
            ->assertRedirect()->assertSessionHasErrors(['cc.0']);

        Http::assertNothingSent();
        $this->assertSame('draft', $booking->fresh()->status);
    }

    public function test_signer_is_required(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $this->attachContact($booking, 'Real Signer', true);

        $this->send($user, $band, $booking, ['signers' => []])
            ->assertRedirect()->assertSessionHasErrors(['signers']);

        Http::assertNothingSent();
    }

    public function test_signer_also_listed_as_cc_is_sent_once_as_signer(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $bride = $this->attachContact($booking, 'Bride Person', true);
        $planner = $this->attachContact($booking, 'Planner Person');

        $this->send($user, $band, $booking, [
            'signers' => [$bride->id],
            'cc'      => [$bride->id, $planner->id],
        ])->assertSessionHasNoErrors();

        $recipients = $this->pandaDocRecipients();
        $this->assertCount(2, $recipients);
        $this->assertCount(1, $recipients->where('email', $bride->email));
        $this->assertSame('signer1', $recipients->firstWhere('email', $bride->email)['role']);
    }
}
