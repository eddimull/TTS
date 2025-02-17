<?php

namespace App\Jobs;

use App\Models\Bookings;
use App\Models\Contracts;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessPandadocWebhookJob extends SpatieProcessWebhookJob
{
    public function handle()
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

    private function handleRecipientCompleted(array $payload)
    {
        $documentId = $payload['data']['id'] ?? null;
        $recipientEmail = $payload['data']['recipient']['email'] ?? null;

        Log::info('Recipient completed document', [
            'documentId' => $documentId,
            'recipientEmail' => $recipientEmail
        ]);

        $contract = Contracts::where('envelope_id', $documentId)->first();
        $contract->status = 'completed';
        $contract->save();

        $this->updateContractAssetURL($contract);

        if ($contract->contractable_type == 'App\Models\Bookings')
        {
            $contract->contractable->status = 'confirmed';
            $contract->contractable->save();
        }
    }

    private function updateContractAssetURL(Contracts $contract)
    {
        // replace the contract asset_url with the signed document
        $asset_url = $contract->contractable->band->site_name . '/' . $contract->contractable->name . '_signed_contract_' . time() . '.pdf';

        $opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Authorization: API-Key " . config('services.pandadoc.api_key')
            )
        );
        $context = stream_context_create($opts);

        Storage::disk('s3')->put(
            $asset_url,
            file_get_contents('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id . '/download', false, $context),
            ['visibility' => 'public']
        );
        $contract->asset_url = Storage::disk('s3')->url($asset_url);
        $contract->save();
    }
}
