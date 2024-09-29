<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Http;

trait Signable
{
    public function sendToPandaDoc()
    {
        $apiKey = config('services.pandadoc.api_key');
        $apiUrl = 'https://api.pandadoc.com/public/v1/documents';

        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($apiUrl, [
            'name' => $this->name, // Assuming your contract has a name field
            'url' => $this->getPdfUrl(), // Method to get the PDF URL
            'recipients' => [
                [
                    'email' => $this->client_email,
                    'first_name' => $this->client_first_name,
                    'last_name' => $this->client_last_name,
                    'role' => 'signer',
                ]
            ],
            'fields' => $this->getSignatureFields(), // Method to define signature fields
        ]);

        if ($response->successful())
        {
            $this->update(['pandadoc_id' => $response->json('id')]);
            return $response->json();
        }

        throw new \Exception('Failed to send document to PandaDoc: ' . $response->body());
    }

    abstract public function getPdfUrl(): string;
    abstract public function getSignatureFields(): array;\

}
