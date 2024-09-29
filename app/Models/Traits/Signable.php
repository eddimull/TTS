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
                $this->update([
                    'envelope_id' => $response->json('id'),
                    'status' => 'sent'
                ]);

                $this->pollDocumentAndSendToRecipients($response->json('id'));
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

    private function pollDocumentAndSendToRecipients($uploadedDocumentId)
    {
        //this can be improved by waiting for a webhook from pandadoc
        //instead of, ya know. sleeping.
        sleep(8);
        $apiKey = config('services.pandadoc.api_key');

        Http::withHeaders([
            'Authorization' => 'API-Key '  . $apiKey
        ])->post('https://api.pandadoc.com/public/v1/documents/' . $uploadedDocumentId . '/send', [
            "message" => 'Please sign this contract so we can make this official!',
            "subject" => 'Contract for ' . $this->contractable->name
        ]);
    }

    abstract public function getPdfUrl(): string;
    abstract public function getSignatureFields(): array;
    abstract public function getContractRecipients(): array;
    abstract public function getContractName(): string;
}
