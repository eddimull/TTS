<?php

namespace App\Jobs;

use App\Exceptions\SignedContractNotReadyException;
use App\Models\Contracts;
use App\Services\ContractCompletionService;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessPandadocWebhookJob extends SpatieProcessWebhookJob
{
    /**
     * PandaDoc fires `recipient_completed` before the signed PDF is rendered, so
     * the first few attempts routinely find the document not ready. Each not-ready
     * attempt releases the job back onto the queue with a growing delay
     * (30s, 60s, 120s, 240s, 480s, then 15m x4 ≈ 75 minutes worst case across
     * the nine releases below) before the job is marked failed. The daily
     * `contracts:check-signed` poller is the backstop after that.
     */
    public int $tries = 10;

    private const MIN_RELEASE_DELAY_SECONDS = 30;
    private const MAX_RELEASE_DELAY_SECONDS = 900;

    public function handle(): void
    {
        $payload = $this->webhookCall->payload;

        Log::info('PandaDoc webhook received', ['payload' => $payload]);

        foreach ($payload as $item)
        {
            Log::info('Payload Item', [$item]);
            if (is_array($item) && array_key_exists('event', $item))
            {
                $this->processPayloadItem($item);
            }
        }
    }

    private function processPayloadItem($item)
    {
        // Process the webhook based on the event type
        switch ($item['event'])
        {
            case 'document_state_changed':
                $this->handleDocumentStateChanged($item);
                break;
            case 'document_updated':
                $this->handleDocumentUpdated($item);
                break;
            case 'recipient_completed':
                $this->handleRecipientCompleted($item);
                break;
                // Add more cases as needed
            default:
                Log::warning('PandaDoc webhook: Unhandled event type', ['event' => $item['status']]);
        }
    }



    private function handleDocumentStateChanged(array $payload): void
    {
        $documentId = $payload['data']['id'] ?? null;
        $newStatus = $payload['data']['status'] ?? null;

        Log::info('Document state changed', [
            'documentId' => $documentId,
            'newStatus' => $newStatus
        ]);

        // `document.completed` is the authoritative "all signatures collected"
        // signal; `recipient_completed` fires earlier, per recipient.
        if ($newStatus === 'document.completed')
        {
            $this->completeContractForEnvelope($documentId);
        }
    }

    private function handleDocumentUpdated(array $payload)
    {
        $documentId = $payload['data']['id'] ?? null;

        Log::info('Document updated', ['documentId' => $documentId]);

        // Add your logic here to handle the document update
        // For example, fetch the latest document details from PandaDoc API and update your local records
    }

    private function handleRecipientCompleted(array $payload): void
    {
        $documentId = $payload['data']['id'] ?? null;
        $recipientEmail = $payload['data']['recipient']['email'] ?? null;

        Log::info('Recipient completed document', [
            'documentId' => $documentId,
            'recipientEmail' => $recipientEmail
        ]);

        $this->completeContractForEnvelope($documentId);
    }

    private function completeContractForEnvelope(?string $documentId): void
    {
        $contract = Contracts::where('envelope_id', $documentId)->first();

        if (!$contract)
        {
            Log::warning('PandaDoc webhook: no contract for envelope', ['documentId' => $documentId]);
            return;
        }

        try
        {
            app(ContractCompletionService::class)->markCompleted($contract);
        }
        catch (SignedContractNotReadyException $e)
        {
            $delay = $this->releaseDelaySeconds($e);

            Log::info('PandaDoc webhook: signed PDF not ready yet, releasing job', [
                'documentId' => $documentId,
                'attempt'    => $this->attempts(),
                'delay'      => $delay,
                'reason'     => $e->getMessage(),
            ]);

            $this->release($delay);
        }
    }

    /**
     * Honour PandaDoc's Retry-After when it sends one, otherwise back off
     * exponentially: 30s, 60s, 120s, 240s, 480s, then capped at 15 minutes.
     */
    private function releaseDelaySeconds(SignedContractNotReadyException $e): int
    {
        if ($e->retryAfterSeconds !== null && $e->retryAfterSeconds > 0)
        {
            return $e->retryAfterSeconds;
        }

        $attempt = max(1, $this->attempts());

        return (int) min(
            self::MIN_RELEASE_DELAY_SECONDS * (2 ** ($attempt - 1)),
            self::MAX_RELEASE_DELAY_SECONDS,
        );
    }
}
