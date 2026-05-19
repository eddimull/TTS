<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\Contracts;
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

    private function storeSignedContractPdf(Contracts $contract): void
    {
        $assetUrl = $contract->contractable->band->site_name . '/'
            . $contract->contractable->name . '_signed_contract_' . time() . '.pdf';

        $response = Http::withHeaders([
            'Authorization' => 'API-Key ' . config('services.pandadoc.api_key'),
        ])->get('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id . '/download');

        $response->throw();

        Storage::disk('s3')->put(
            $assetUrl,
            $response->body(),
            ['visibility' => 'public']
        );

        $contract->asset_url = '/' . ltrim($assetUrl, '/');
        $contract->save();
    }
}
