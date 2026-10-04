<?php

namespace Tests\Feature;

use App\Jobs\ProcessPandadocWebhookJob;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
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
            'api.pandadoc.com/public/v1/documents/*' => Http::response(['status' => 'document.completed'], 200),
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

    public function test_recipient_completed_webhook_with_unknown_envelope_does_not_throw(): void
    {
        Storage::fake('s3');
        Http::fake();

        $webhookCall = WebhookCall::create([
            'name'    => 'pandadoc',
            'url'     => 'https://tts.band/webhooks/pandadoc',
            'payload' => [
                [
                    'event' => 'recipient_completed',
                    'data'  => [
                        'id'        => 'envelope-that-does-not-exist',
                        'recipient' => ['email' => 'nobody@example.com'],
                    ],
                ],
            ],
        ]);

        (new ProcessPandadocWebhookJob($webhookCall))->handle();

        // No contract matches the envelope id, so nothing should be created or
        // changed and no PandaDoc call should be made.
        $this->assertSame(0, \App\Models\Contracts::count());
        Http::assertNothingSent();
    }

    private function makeContract(string $envelopeId): Contracts
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'contract_option' => 'default',
        ]);

        return $booking->contract()->create([
            'envelope_id' => $envelopeId,
            'author_id'   => $user->id,
            'status'      => 'sent',
        ]);
    }

    private function makeWebhookCall(array $items): WebhookCall
    {
        return WebhookCall::create([
            'name'    => 'pandadoc',
            'url'     => 'https://tts.band/webhooks/pandadoc',
            'payload' => $items,
        ]);
    }

    public function test_document_state_changed_to_completed_completes_contract(): void
    {
        Storage::fake('s3');
        Http::fake([
            'api.pandadoc.com/public/v1/documents/*/download' => Http::response('PDFBYTES', 200),
            'api.pandadoc.com/public/v1/documents/*' => Http::response(['status' => 'document.completed'], 200),
        ]);

        $contract = $this->makeContract('env-state-1');

        $webhookCall = $this->makeWebhookCall([
            [
                'event' => 'document_state_changed',
                'data'  => ['id' => 'env-state-1', 'status' => 'document.completed'],
            ],
        ]);

        (new ProcessPandadocWebhookJob($webhookCall))->handle();

        $this->assertSame('completed', $contract->fresh()->status);
    }

    public function test_document_state_changed_to_non_completed_status_is_ignored(): void
    {
        Storage::fake('s3');
        Http::fake();

        $contract = $this->makeContract('env-state-2');

        $webhookCall = $this->makeWebhookCall([
            [
                'event' => 'document_state_changed',
                'data'  => ['id' => 'env-state-2', 'status' => 'document.viewed'],
            ],
        ]);

        (new ProcessPandadocWebhookJob($webhookCall))->handle();

        $this->assertSame('sent', $contract->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_job_releases_itself_when_signed_pdf_is_not_ready_yet(): void
    {
        Storage::fake('s3');
        Http::fake([
            'api.pandadoc.com/public/v1/documents/*/download' => Http::response('', 202, ['Retry-After' => '45']),
            'api.pandadoc.com/public/v1/documents/*' => Http::response(['status' => 'document.completed'], 200),
        ]);

        $contract = $this->makeContract('env-not-ready');

        $webhookCall = $this->makeWebhookCall([
            [
                'event' => 'recipient_completed',
                'data'  => ['id' => 'env-not-ready', 'recipient' => ['email' => 'signer@example.com']],
            ],
        ]);

        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('isReleased')->andReturn(false);
        $queueJob->shouldReceive('release')->once()->with(45);

        $job = new ProcessPandadocWebhookJob($webhookCall);
        $job->setJob($queueJob);
        $job->handle();

        $this->assertSame('sent', $contract->fresh()->status);
    }

    public function test_release_delay_backs_off_with_attempts_when_pandadoc_gives_no_retry_after(): void
    {
        Storage::fake('s3');
        Http::fake([
            'api.pandadoc.com/public/v1/documents/*' => Http::response(['status' => 'document.viewed'], 200),
        ]);

        $this->makeContract('env-backoff');

        $webhookCall = $this->makeWebhookCall([
            [
                'event' => 'recipient_completed',
                'data'  => ['id' => 'env-backoff', 'recipient' => ['email' => 'signer@example.com']],
            ],
        ]);

        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(3);
        $queueJob->shouldReceive('isReleased')->andReturn(false);
        // attempt 1 → 30s, attempt 2 → 60s, attempt 3 → 120s
        $queueJob->shouldReceive('release')->once()->with(120);

        $job = new ProcessPandadocWebhookJob($webhookCall);
        $job->setJob($queueJob);
        $job->handle();
    }

    public function test_job_retries_enough_times_to_outlast_pandadoc_processing(): void
    {
        $job = new ProcessPandadocWebhookJob($this->makeWebhookCall([]));

        $this->assertGreaterThanOrEqual(8, $job->tries);
    }
}
