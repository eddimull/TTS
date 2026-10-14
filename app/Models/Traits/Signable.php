<?php

namespace App\Models\Traits;

use App\Exceptions\PandaDocVoidException;
use App\Models\Contacts;
use App\Services\PandaDocService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

trait Signable
{
    /**
     * Create the PandaDoc document for this contract and send it out.
     *
     * Every signer gets its own role (signer1, signer2, ...) matching the
     * `{signature:signerN}` field tags in the generated PDF; they sign in
     * parallel (no signing_order). PandaDoc does not allow underscores in role
     * names on a file with field tags, hence `signer1` and `cc1`, not
     * `signer_1`/`CC_1`. A contact that is both a signer and a CC is sent
     * as a signer only.
     *
     * @param  Contacts|Collection<int, Contacts>  $signers
     * @param  Collection<int, Contacts>|null  $ccRecipients
     */
    public function sendToPandaDoc(Contacts|Collection $signers, ?Collection $ccRecipients = null)
    {
        $apiKey = config('services.pandadoc.api_key');
        $apiUrl = 'https://api.pandadoc.com/public/v1/documents';

        $signers = $signers instanceof Contacts ? collect([$signers]) : collect($signers);
        $signers = $signers->filter()->unique(fn (Contacts $contact) => $contact->getKey())->values();

        if ($signers->isEmpty())
        {
            throw new \InvalidArgumentException('At least one signer is required to send a contract.');
        }

        $signerIds = $signers->map(fn (Contacts $contact) => $contact->getKey())->all();
        $signerEmails = $signers->map(fn (Contacts $contact) => strtolower((string) $contact->email))->all();

        $ccRecipients = collect($ccRecipients ?? [])
            ->filter()
            ->unique(fn (Contacts $contact) => $contact->getKey())
            ->reject(fn (Contacts $contact) => in_array($contact->getKey(), $signerIds, true)
                || in_array(strtolower((string) $contact->email), $signerEmails, true))
            ->values();

        // Potential recipients as the contractable formats them (name split etc.)
        $knownRecipients = collect($this->getContractRecipients());

        $signerRecipients = $signers->map(function (Contacts $contact, int $index) use ($knownRecipients)
        {
            return array_merge(
                $this->recipientPayloadFor($contact, $knownRecipients),
                ['role' => self::signerRole($index)]
            );
        })->all();

        $ccRecipientsArray = $ccRecipients->map(function (Contacts $contact, int $index) use ($knownRecipients)
        {
            return array_merge(
                $this->recipientPayloadFor($contact, $knownRecipients),
                ['recipient_type' => 'CC', 'role' => 'cc' . ($index + 1)]
            );
        })->all();

        $recipients = array_merge($signerRecipients, $ccRecipientsArray);

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
                'fields' => $this->getSignatureFields($signerRecipients),
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

    /**
     * PandaDoc role for the signer at the given zero-based position. Must
     * match the `{signature:<role>}` tag rendered in the contract PDF.
     */
    public static function signerRole(int $index): string
    {
        return 'signer' . ($index + 1);
    }

    private function recipientPayloadFor(Contacts $contact, Collection $knownRecipients): array
    {
        $known = $knownRecipients->first(
            fn (array $recipient) => strcasecmp((string) ($recipient['email'] ?? ''), (string) $contact->email) === 0
        );

        if ($known)
        {
            return $known;
        }

        $nameParts = explode(' ', trim((string) $contact->name));

        return [
            'email' => $contact->email,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
        ];
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

    /**
     * Void the PandaDoc document for this contract so it can be amended.
     *
     * A 404 means the document was already deleted (e.g. by hand in the
     * PandaDoc dashboard) — treated as success so amendment is idempotent.
     * If the void is refused but the document can no longer be signed anyway
     * (document.voided, which is how PandaDoc reports expired documents, or
     * document.declined) that is also treated as success.
     * A document that was already signed throws InvalidArgumentException;
     * any other failure throws PandaDocVoidException. On throw the caller
     * must not mutate local state.
     */
    public function voidPandaDocDocument(): void
    {
        if (!$this->envelope_id)
        {
            return;
        }

        $apiKey = config('services.pandadoc.api_key');

        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->patch("https://api.pandadoc.com/public/v1/documents/{$this->envelope_id}/status", [
            'status' => 11, // document.voided
        ]);

        if ($response->successful() || $response->status() === 404)
        {
            return;
        }

        $currentStatus = null;
        try
        {
            $currentStatus = $this->documentStatus()['status'] ?? null;
        }
        catch (\Exception)
        {
            // Fall through and report the original void failure.
        }

        if (in_array($currentStatus, ['document.voided', 'document.declined'], true))
        {
            return;
        }

        if ($currentStatus === 'document.completed')
        {
            throw new \InvalidArgumentException('This contract has already been signed and can no longer be amended.');
        }

        throw new PandaDocVoidException($this->envelope_id, $response->status(), $response->body());
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
    abstract public function getSignatureFields(array $signerRecipients): array;
    abstract public function getContractRecipients(): array;
    abstract public function getContractName(): string;
}
