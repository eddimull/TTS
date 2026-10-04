<?php

namespace App\Services;

use App\Exceptions\SignedContractNotReadyException;
use App\Models\Bookings;
use App\Models\Contracts;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContractCompletionService
{
    public function markCompleted(Contracts $contract): void
    {
        if ($contract->status === 'completed')
        {
            return;
        }

        $this->storeSignedContractPdf($contract);

        $contract->status = 'completed';
        $contract->save();

        if ($contract->contractable_type === Bookings::class) {
            $contract->contractable->status = 'confirmed';
            $contract->contractable->save();

            $portalService = new ContactPortalService();
            try {
                $portalService->grantPortalAccessAfterContractCompletion($contract->contractable);
            } catch (\Exception $e) {
                Log::error('Failed to grant portal access after contract completion', [
                    'booking_id' => $contract->contractable->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Download the signed PDF from PandaDoc and store it on S3.
     *
     * @throws SignedContractNotReadyException when PandaDoc has not finished
     *         producing the signed document yet. The `recipient_completed`
     *         webhook fires before the document reaches `document.completed`
     *         and before the final PDF is rendered, so the caller must retry.
     */
    private function storeSignedContractPdf(Contracts $contract): void
    {
        $this->assertDocumentCompleted($contract);

        $response = $this->pandaDoc()
            ->get('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id . '/download');

        if ($response->status() === 202 || $response->status() === 409)
        {
            throw new SignedContractNotReadyException(
                "PandaDoc has not finished generating the signed PDF for envelope {$contract->envelope_id} (HTTP {$response->status()})",
                $this->retryAfterSeconds($response),
            );
        }

        $response->throw();

        if ($response->body() === '')
        {
            throw new SignedContractNotReadyException(
                "PandaDoc returned an empty PDF for envelope {$contract->envelope_id}",
            );
        }

        $assetUrl = $contract->contractable->band->site_name . '/'
            . $contract->contractable->name . '_signed_contract_' . time() . '.pdf';

        Storage::disk('s3')->put(
            $assetUrl,
            $response->body(),
            ['visibility' => 'public']
        );

        $contract->asset_url = '/' . ltrim($assetUrl, '/');
        $contract->save();
    }

    private function assertDocumentCompleted(Contracts $contract): void
    {
        $response = $this->pandaDoc()
            ->acceptJson()
            ->get('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id);

        $response->throw();

        $status = $response->json('status');

        if ($status !== 'document.completed')
        {
            throw new SignedContractNotReadyException(
                "PandaDoc envelope {$contract->envelope_id} is not completed yet (status: " . ($status ?? 'unknown') . ")",
            );
        }
    }

    private function pandaDoc(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'API-Key ' . config('services.pandadoc.api_key'),
        ]);
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) ? max(0, (int) $header) : null;
    }
}
