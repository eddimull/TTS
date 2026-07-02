<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\User;
use App\Services\PdfGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end regression coverage for TTS-BAND-158.
 *
 * The mobile contract/send endpoint used to resolve a single `cc_id` with
 * ->find($id) (a single Contacts model) and hand it to
 * sendToPandaDoc(Contacts $signer, ?Eloquent\Collection $ccRecipients), which
 * blew up with a TypeError. This test drives the real controller over HTTP with
 * a single cc_id, so it fails with the original TypeError if the fix is reverted.
 */
class SendContractCcRecipientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.pandadoc.api_key', 'fake-api-key');

        // Fake S3 so getContractPdf()/storeContractPdf() don't touch a real bucket.
        Storage::fake('s3');

        // Swap the real Browsershot-backed PDF generator for a stub so we never
        // spin up headless Chromium during the test.
        $this->app->bind(PdfGeneratorService::class, function () {
            return new class extends PdfGeneratorService {
                public function generateFromHtml(string $html, string $format = 'Legal', bool $taggedPdf = false): string
                {
                    return '%PDF-1.4 fake contract bytes';
                }
            };
        });
    }

    public function test_send_contract_with_single_cc_id_coerces_to_collection(): void
    {
        Http::fake([
            'api.pandadoc.com/*' => Http::response([
                'id'     => 'fake-pandadoc-id',
                'status' => 'document.draft',
            ], 201),
        ]);

        $user = User::factory()->create();
        // A valid address is required or getContractPdf() throws before we ever
        // reach the cc_id coercion we are trying to exercise.
        $band = Bands::factory()->create([
            'address' => '123 Main',
            'city'    => 'New Orleans',
            'state'   => 'LA',
            'zip'     => '70112',
            'logo'    => '/images/logo.png',
        ]);
        $band->owners()->create(['user_id' => $user->id]);

        // getContractPdf() reads the band logo off S3; put a fake asset there.
        Storage::disk('s3')->put('logo.png', 'fake-png-bytes');

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'draft',
        ]);

        // In the real flow the app saves contract terms before sending; the
        // contract PDF view iterates $booking->contract->custom_terms.
        $booking->contract()->create([
            'author_id'    => $user->id,
            'status'       => 'pending',
            'custom_terms' => [
                ['title' => 'Cancellation', 'content' => 'No refunds within 7 days.'],
            ],
        ]);

        $signer = Contacts::factory()->create(['band_id' => $band->id]);
        $cc     = Contacts::factory()->create(['band_id' => $band->id]);
        $booking->contacts()->attach($signer, ['role' => 'Primary', 'is_primary' => true]);
        $booking->contacts()->attach($cc, ['role' => 'CC']);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/send", [
                'signer_id' => $signer->id,
                'cc_id'     => $cc->id,
            ]);

        $response->assertOk();

        // Prove the coercion worked end-to-end: PandaDoc was called with both the
        // signer and the single CC recipient in the payload. Assert on presence
        // (not position) so the test isn't coupled to sendToPandaDoc()'s
        // recipient ordering.
        Http::assertSent(function ($request) use ($signer, $cc) {
            if ($request->url() !== 'https://api.pandadoc.com/public/v1/documents') {
                return false;
            }

            $recipients = collect($request['recipients'] ?? []);

            return $recipients->contains(fn ($r) => ($r['email'] ?? null) === $signer->email)
                && $recipients->contains(
                    fn ($r) => ($r['email'] ?? null) === $cc->email && ($r['recipient_type'] ?? null) === 'CC'
                );
        });

        $this->assertSame('pending', $booking->fresh()->status);
    }
}
