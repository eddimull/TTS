<?php

namespace App\Jobs;

use App\Models\Contracts;
use App\Services\ContractCompletionService;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessPandadocWebhookJob extends SpatieProcessWebhookJob
{
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



    private function handleDocumentStateChanged(array $payload)
    {
        $documentId = $payload['data']['id'] ?? null;
        $newStatus = $payload['data']['status'] ?? null;

        Log::info('Document state changed', [
            'documentId' => $documentId,
            'newStatus' => $newStatus
        ]);

        // Add your logic here to handle the document state change
        // For example, update your local database, trigger notifications, etc.
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

        $contract = Contracts::where('envelope_id', $documentId)->first();

        if (!$contract)
        {
            Log::warning('PandaDoc webhook: no contract for envelope', ['documentId' => $documentId]);
            return;
        }

        app(ContractCompletionService::class)->markCompleted($contract);
    }
}
