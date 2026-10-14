<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsContractSendFixtures;
use Tests\TestCase;

class SendContractMultipleSignersTest extends TestCase
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
        $token = $user->createToken('test-device')->plainTextToken;

        return $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/send", $payload);
    }

    public function test_multiple_signers_each_get_their_own_role_and_signature_tag(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $bride = $this->attachContact($booking, 'Bride Person', true);
        $groom = $this->attachContact($booking, 'Groom Person');
        $planner = $this->attachContact($booking, 'Planner Person');

        $response = $this->send($user, $band, $booking, [
            'signer_ids' => [$bride->id, $groom->id],
            'cc_ids'     => [$planner->id],
        ]);

        $response->assertOk()->assertJsonStructure(['booking' => ['id', 'status']]);

        $recipients = $this->pandaDocRecipients();
        $this->assertCount(3, $recipients);

        $signers = $recipients->filter(fn ($r) => !isset($r['recipient_type']))->values();
        $this->assertSame([$bride->email, $groom->email], $signers->pluck('email')->all());
        $this->assertSame(['signer1', 'signer2'], $signers->pluck('role')->all());

        $cc = $recipients->firstWhere('email', $planner->email);
        $this->assertSame('CC', $cc['recipient_type']);
        $this->assertSame('cc1', $cc['role']);

        // No role may contain an underscore (PandaDoc rejects those with field tags),
        // and no signing order is sent: signers sign in parallel.
        $recipients->each(fn ($r) => $this->assertStringNotContainsString('_', $r['role']));
        $recipients->each(fn ($r) => $this->assertArrayNotHasKey('signing_order', $r));

        $fields = $this->pandaDocCreateRequest()->data()['fields'];
        $this->assertSame(['signer1', 'signer2'], array_values(array_map(fn ($f) => $f['role'], $fields)));

        $html = $this->renderedContractHtml[0];
        $this->assertSame(1, substr_count($html, '{signature:signer1_'));
        $this->assertSame(1, substr_count($html, '{signature:signer2_'));
        $this->assertSame(2, substr_count($html, '{signature:'));
        $this->assertStringContainsString('with <strong>Bride Person and Groom Person</strong>', $html);

        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_legacy_signer_id_and_cc_id_payload_still_works(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $signer = $this->attachContact($booking, 'Solo Signer', true);
        $cc = $this->attachContact($booking, 'Copied Person');

        $this->send($user, $band, $booking, [
            'signer_id' => $signer->id,
            'cc_id'     => $cc->id,
        ])->assertOk();

        $recipients = $this->pandaDocRecipients();
        $this->assertCount(2, $recipients);
        $this->assertSame('signer1', $recipients->firstWhere('email', $signer->email)['role']);
        $this->assertSame('CC', $recipients->firstWhere('email', $cc->email)['recipient_type']);
        $this->assertSame(1, substr_count($this->renderedContractHtml[0], '{signature:'));
    }

    public function test_signer_from_another_booking_is_rejected(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $this->attachContact($booking, 'Real Signer', true);
        $stranger = Contacts::factory()->create(['band_id' => $band->id]);

        $this->send($user, $band, $booking, ['signer_ids' => [$stranger->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['signer_ids.0']);

        Http::assertNothingSent();
        $this->assertSame('draft', $booking->fresh()->status);
    }

    public function test_legacy_signer_id_from_another_booking_is_rejected(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $this->attachContact($booking, 'Real Signer', true);
        $stranger = Contacts::factory()->create(['band_id' => $band->id]);

        $this->send($user, $band, $booking, ['signer_id' => $stranger->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Signer contact not found on this booking.');

        Http::assertNothingSent();
    }

    public function test_cc_from_another_booking_is_rejected(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $signer = $this->attachContact($booking, 'Real Signer', true);
        $stranger = Contacts::factory()->create(['band_id' => $band->id]);

        $this->send($user, $band, $booking, [
            'signer_ids' => [$signer->id],
            'cc_ids'     => [$stranger->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['cc_ids.0']);

        Http::assertNothingSent();
    }

    public function test_signers_are_required_and_must_be_distinct(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $signer = $this->attachContact($booking, 'Real Signer', true);

        $this->send($user, $band, $booking, ['signer_ids' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['signer_ids']);

        $this->send($user, $band, $booking, ['signer_ids' => [$signer->id, $signer->id]])
            ->assertStatus(422)->assertJsonValidationErrors(['signer_ids.0']);

        $this->send($user, $band, $booking, ['signer_ids' => ['abc']])
            ->assertStatus(422)->assertJsonValidationErrors(['signer_ids.0']);

        Http::assertNothingSent();
    }

    public function test_contact_chosen_as_signer_and_cc_is_only_sent_as_signer(): void
    {
        [$user, $band, $booking] = $this->makeSendableBooking();
        $bride = $this->attachContact($booking, 'Bride Person', true);
        $groom = $this->attachContact($booking, 'Groom Person');
        $planner = $this->attachContact($booking, 'Planner Person');

        $this->send($user, $band, $booking, [
            'signer_ids' => [$bride->id, $groom->id],
            'cc_ids'     => [$groom->id, $planner->id],
        ])->assertOk();

        $recipients = $this->pandaDocRecipients();
        $this->assertCount(3, $recipients);
        $this->assertCount(1, $recipients->where('email', $groom->email));
        $this->assertSame('signer2', $recipients->firstWhere('email', $groom->email)['role']);
        $this->assertSame('cc1', $recipients->firstWhere('email', $planner->email)['role']);
    }
}
