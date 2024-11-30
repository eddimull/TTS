<?php

namespace App\Models\Traits;

use App\Models\Contacts;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

trait Signable
{
    public function sendToPandaDoc(Contacts $contact = null)
    {
        $apiKey = config('services.pandadoc.api_key');
        $apiUrl = 'https://api.pandadoc.com/public/v1/documents';

        $recipients = $this->getContractRecipients();

        if ($contact)
        {
            $recipients = array_values(array_filter($recipients, function ($recipient) use ($contact)
            {
                return $recipient['email'] === $contact->email;
            }));
        }

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
                'recipients' => $recipients,
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

    public function auditTrail()
    {
        $accessToken = config('services.pandadoc.access_token');

        $documentId = $this->envelope_id;
        $auditUrl = "https://api.pandadoc.com/documents/{$documentId}/audit_trail";
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->get($auditUrl);

        if ($response->successful())
        {
            return $response->json();
        }

        Log::error('Failed to get audit trail: ' . $response->body());
        throw new \Exception('Failed to get audit trail: ' . $response->body());
    }

    public function documentStatus()
    {
        $apiKey = config('services.pandadoc.api_key');
        $documentId = $this->envelope_id;

        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $apiKey,
        ])->get("https://api.pandadoc.com/public/v1/documents/{$documentId}");

        if ($response->successful())
        {
            return $response->json();
        }

        Log::error('Failed to get document status: ' . $response->body());
        throw new \Exception('Failed to get document status: ' . $response->body());
    }

    abstract public function getPdfUrl(): string;
    abstract public function getSignatureFields(): array;
    abstract public function getContractRecipients(): array;
    abstract public function getContractName(): string;
}
