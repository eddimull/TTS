<?php

namespace Tests\Feature;

use App\Jobs\ProcessPandadocWebhookJob;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\WebhookClient\Models\WebhookCall;
use Tests\TestCase;

class ProcessPandadocWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_completed_webhook_completes_contract(): void
    {
        Storage::fake('s3');
        Http::fake([
            'api.pandadoc.com/public/v1/documents/*/download' => Http::response('PDFBYTES', 200),
        ]);

        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'contract_option' => 'default',
        ]);
        $contract = $booking->contract()->create([
            'envelope_id' => 'env-webhook-1',
            'author_id'   => $user->id,
            'status'      => 'sent',
        ]);

        $webhookCall = WebhookCall::create([
            'name'    => 'pandadoc',
            'url'     => 'https://tts.band/webhooks/pandadoc',
            'payload' => [
                [
                    'event' => 'recipient_completed',
                    'data'  => [
                        'id'        => 'env-webhook-1',
                        'recipient' => ['email' => 'signer@example.com'],
                    ],
                ],
            ],
        ]);

        (new ProcessPandadocWebhookJob($webhookCall))->handle();

        $contract->refresh();
        $this->assertSame('completed', $contract->status);
        $this->assertSame('confirmed', $contract->contractable->status);
    }
}
