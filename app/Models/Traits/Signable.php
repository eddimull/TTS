<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

trait Signable
{
    public function sendToPandaDoc()
    {
        $apiKey = config('services.pandadoc.api_key');
        $apiUrl = 'https://api.pandadoc.com/public/v1/documents';

        try
        {
            $response = Http::withHeaders([
                'Authorization' => 'API-Key ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl, [
                'name' => $this->getContractName(),
                'tags' => [
                    $this->contractable->band->name
                ],
                'url' => $this->getPdfUrl(),
                'recipients' => $this->getContractRecipients(),
                'fields' => $this->getSignatureFields(),
                'parse_form_fields' => false,
            ]);

            if ($response->successful())
            {
                $documentId = $response->json('id');
                $this->update([
                    'envelope_id' => $documentId,
                    // 'status' => 'processing'
                ]);

                // Poll for document status
                $this->pollDocumentStatus($documentId);

                return $response->json();
            }

            Log::error('Failed to send document to PandaDoc: ' . $response->body());
            throw new \Exception('Failed to send document to PandaDoc: ' . $response->body());
        }
        catch (\Exception $e)
        {
            Log::error('Exception while sending document to PandaDoc: ' . $e->getMessage());
            throw $e;
        }
    }

    private function pollDocumentStatus($documentId)
    {
        $apiKey = config('services.pandadoc.api_key');
        $statusUrl = "https://api.pandadoc.com/public/v1/documents/{$documentId}";

        $maxAttempts = 10;
        $attempt = 0;
        $delay = 5; // seconds

        while ($attempt < $maxAttempts)
        {
            Log::info("Polling document status for document ID {$documentId} (attempt {$attempt})");

            $response = Http::withHeaders([
                'Authorization' => 'API-Key ' . $apiKey,
            ])->get($statusUrl);

            Log::info($response->json());

            if ($response->successful())
            {
                $status = $response->json('status');
                if ($status === 'document.draft')
                {
                    $this->update(['status' => 'sent']);
                    $this->sendToRecipients($documentId);
                    return;
                }
            }

            $attempt++;
            sleep($delay);
        }

        Log::error("Document status did not change to 'document.draft' after {$maxAttempts} attempts.");
        throw new \Exception("Document processing timed out.");
    }

    private function sendToRecipients($documentId)
    {
        $apiKey = config('services.pandadoc.api_key');

        $response = Http::withHeaders([
            'Authorization' => 'API-Key '  . $apiKey
        ])->post("https://api.pandadoc.com/public/v1/documents/{$documentId}/send", [
            "message" => 'Please sign this contract so we can make this official!',
            "subject" => 'Contract for ' . $this->contractable->name
        ]);

        if ($response->successful())
        {
            $this->update(['status' => 'sent']);
        }
        else
        {
            Log::error('Failed to send document to recipients: ' . $response->body());
            throw new \Exception('Failed to send document to recipients: ' . $response->body());
        }
    }

    abstract public function getPdfUrl(): string;
    abstract public function getSignatureFields(): array;
    abstract public function getContractRecipients(): array;
    abstract public function getContractName(): string;
}
