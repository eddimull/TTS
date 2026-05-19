<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\Contracts;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContractCompletionService
{
    public function markCompleted(Contracts $contract): void
    {
        $contract->status = 'completed';
        $contract->save();

        $this->updateContractAssetURL($contract);

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

    private function updateContractAssetURL(Contracts $contract): void
    {
        $asset_url = $contract->contractable->band->site_name . '/'
            . $contract->contractable->name . '_signed_contract_' . time() . '.pdf';

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: API-Key ' . config('services.pandadoc.api_key'),
            ],
        ];
        $context = stream_context_create($opts);

        Storage::disk('s3')->put(
            $asset_url,
            file_get_contents(
                'https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id . '/download',
                false,
                $context
            ),
            ['visibility' => 'public']
        );

        $contract->asset_url = '/' . ltrim($asset_url, '/');
        $contract->save();
    }
}
