<?php

namespace App\Models\Traits;

use App\Models\Contacts;
use App\Services\PandaDocService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Collection;

trait Signable
{
    public function sendToPandaDoc(Contacts $signer, ?Collection $ccRecipients = null)
    {
        $apiKey = config('services.pandadoc.api_key');
        $apiUrl = 'https://api.pandadoc.com/public/v1/documents';

        // Get all potential recipients
        $recipients = $this->getContractRecipients();

        // Filter to keep only the signer
        $signerRecipient = array_values(array_filter($recipients, function ($recipient) use ($signer)
        {
            return $recipient['email'] === $signer->email;
        }));

        // If we have CC recipients, add them to the recipients array
        if ($ccRecipients && $ccRecipients->isNotEmpty())
        {
            $ccRecipientsArray = $ccRecipients->map(function ($ccContact, $index) use ($recipients)
            {
                // Find matching recipient from original recipients array
                $matchingRecipient = collect($recipients)->first(function ($recipient) use ($ccContact)
                {
                    return $recipient['email'] === $ccContact->email;
                });

                if ($matchingRecipient)
                {
                    return array_merge($matchingRecipient, ['recipient_type' => 'CC', 'role' => 'CC_' . ($index + 1)]);
                }

                // If no matching recipient found, create a new CC recipient
                return [
                    'email' => $ccContact->email,
                    'recipient_type' => 'CC',
                    'role' => 'CC_' . ($index + 1),
                ];
            })->all();

            // Combine signer and CC recipients
            $recipients = array_merge($signerRecipient, $ccRecipientsArray);
        }
        else
        {
            $recipients = $signerRecipient;
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
        $pandaDocService = new PandaDocService();

        $documentId = $this->envelope_id;
        $auditUrl = "https://api.pandadoc.com/public/v2/documents/{$documentId}/audit-trail";
        $response = $pandaDocService->makeAuthenticatedRequest('get', $auditUrl);
        if ($response->successful()) {
            $data = $response->json();
            
            // Transform the raw audit trail into readable format
            if (isset($data['results'])) {
                $data['results'] = array_map(function($event) {
                    return $this->transformAuditEvent($event);
                }, $data['results']);
            }
            
            return $data;
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


    private function transformAuditEvent($event)
    {
        // PandaDoc action code mappings
        $actionMap = [
            1 => 'Document Created',
            2 => 'Document Updated', 
            3 => 'Document Deleted',
            4 => 'Document Restored',
            5 => 'Document Duplicated',
            6 => 'Document Sent',
            7 => 'Document Signed',
            8 => 'Document Viewed',
            9 => 'Document Downloaded',
            10 => 'Document Completed',
            11 => 'Document Declined',
            12 => 'Document Voided',
            13 => 'Document Expired',
            14 => 'Payment Requested',
            15 => 'Payment Completed',
            16 => 'Payment Failed',
            17 => 'Reminder Sent',
            18 => 'Comment Added',
            19 => 'Approval Requested',
            20 => 'Approval Granted',
            21 => 'Approval Denied',
            22 => 'Document Archived',
            23 => 'Document Unarchived',
            24 => 'Document Shared',
            25 => 'Document Unshared',
        ];

        $actionCode = $event['action'] ?? 0;
        $actionName = $actionMap[$actionCode] ?? "Unknown Action ({$actionCode})";
        
        return [
            'id' => $event['id'],
            'action' => $actionName,
            'action_code' => $actionCode,
            'user_email' => $event['user']['email'] ?? 'Unknown',
            'user_id' => $event['user']['id'] ?? null,
            'description' => $this->getActionDescription($actionCode, $event),
            'created_at' => $event['date_created'],
            'ip_address' => $event['ip_address'] ?? null,
            'reason' => $event['reason'],
            'status' => $this->getEventStatus($actionCode)
        ];
    }

    private function getActionDescription($actionCode, $event)
    {
        $userEmail = $event['user']['email'] ?? 'Unknown user';
        
        $descriptions = [
            1 => "Document was created by {$userEmail}",
            2 => "Document was updated by {$userEmail}",
            3 => "Document was deleted by {$userEmail}",
            4 => "Document was restored by {$userEmail}",
            5 => "Document was duplicated by {$userEmail}",
            6 => "Document was sent to recipients by {$userEmail}",
            7 => "Document was signed by {$userEmail}",
            8 => "Document was viewed by {$userEmail}",
            9 => "Document was downloaded by {$userEmail}",
            10 => "Document was completed (all signatures collected)",
            11 => "Document was declined by {$userEmail}",
            12 => "Document was voided by {$userEmail}",
            13 => "Document expired",
            14 => "Payment was requested",
            15 => "Payment was completed",
            16 => "Payment failed",
            17 => "Reminder was sent",
            18 => "Comment was added by {$userEmail}",
            19 => "Approval was requested",
            20 => "Document was approved by {$userEmail}",
            21 => "Document was denied by {$userEmail}",
            22 => "Document was archived by {$userEmail}",
            23 => "Document was unarchived by {$userEmail}",
            24 => "Document was shared by {$userEmail}",
            25 => "Document sharing was removed by {$userEmail}",
        ];

        return $descriptions[$actionCode] ?? "Unknown action performed by {$userEmail}";
    }

    private function getEventStatus($actionCode)
    {
        // Map action codes to status categories
        $completedActions = [1, 6, 7, 8, 9, 10, 15, 18, 20, 22, 24];
        $failedActions = [3, 11, 12, 13, 16, 21];
        $pendingActions = [14, 17, 19];
        
        if (in_array($actionCode, $completedActions)) {
            return 'completed';
        } elseif (in_array($actionCode, $failedActions)) {
            return 'failed';
        } elseif (in_array($actionCode, $pendingActions)) {
            return 'pending';
        }
        
        return 'info';
    }

    abstract public function getPdfUrl(): string;
    abstract public function getSignatureFields(): array;
    abstract public function getContractRecipients(): array;
    abstract public function getContractName(): string;
}
