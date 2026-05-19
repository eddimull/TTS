<?php

namespace App\Console\Commands;

use App\Models\Contracts;
use App\Services\ContractCompletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckSignedContracts extends Command
{
    protected $signature = 'contracts:check-signed';

    protected $description = 'Poll PandaDoc for signed contracts whose completion webhook was missed';

    public function handle(ContractCompletionService $completionService): int
    {
        $this->info('Checking for signed contracts...');

        $contracts = Contracts::query()
            ->with('contractable.band')
            ->where('status', '!=', 'completed')
            ->where('created_at', '>', Carbon::now()->subMonths(2))
            ->whereNotNull('envelope_id')
            ->get();

        $processedCount = 0;
        $errorCount = 0;

        foreach ($contracts as $contract)
        {
            try
            {
                $response = Http::withHeaders([
                    'Authorization' => 'API-Key ' . config('services.pandadoc.api_key'),
                ])
                    ->acceptJson()
                    ->get('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id);

                if (!$response->ok())
                {
                    Log::warning('Failed to fetch PandaDoc status', [
                        'contract_id' => $contract->id,
                        'envelope_id' => $contract->envelope_id,
                        'status'      => $response->status(),
                    ]);
                    $errorCount++;
                    continue;
                }

                if ($response->json('status') === 'document.completed')
                {
                    $completionService->markCompleted($contract);
                    $this->line("Completed contract for: " . ($contract->contractable?->name ?? 'unknown'));
                    $processedCount++;
                }
            }
            catch (\Exception $e)
            {
                Log::error('Error processing contract', [
                    'contract_id' => $contract->id,
                    'error'       => $e->getMessage(),
                ]);
                $errorCount++;
            }
        }

        $this->info("Processed {$processedCount} completed contracts.");

        if ($errorCount > 0)
        {
            $this->warn("Encountered {$errorCount} errors. Check logs for details.");
        }

        return self::SUCCESS;
    }
}
