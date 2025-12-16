<?php

namespace App\Console\Commands;

use App\Models\ProposalContracts;
use App\Models\User;
use App\Notifications\TTSNotification;
use App\Services\ProposalServices;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CheckSignedContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:check-signed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check PandaDoc for signed contracts and process completed ones';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for signed contracts...');

        $contracts = ProposalContracts::where('status', '!=', 'completed')
            ->where('created_at', '>', Carbon::now()->subMonths(2))
            ->get();

        $processedCount = 0;
        $errorCount = 0;

        foreach ($contracts as $contract) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'API-Key ' . env('PANDADOC_KEY')
                ])
                    ->acceptJson()
                    ->get('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id);

                if (!$response->ok()) {
                    Log::warning('Failed to fetch PandaDoc status', [
                        'contract_id' => $contract->id,
                        'envelope_id' => $contract->envelope_id,
                        'status' => $response->status()
                    ]);
                    $errorCount++;
                    continue;
                }

                if ($response['status'] == "document.completed") {
                    $this->processCompletedContract($contract);
                    $processedCount++;
                }
            } catch (\Exception $e) {
                Log::error('Error processing contract', [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }
        }

        $this->info("Processed {$processedCount} completed contracts.");

        if ($errorCount > 0) {
            $this->warn("Encountered {$errorCount} errors. Check logs for details.");
        }

        return 0;
    }

    /**
     * Process a completed contract
     */
    private function processCompletedContract(ProposalContracts $contract): void
    {
        $proposal = $contract->proposal;
        $proposal->phase_id = 6;
        $proposal->save();
        $contract->status = 'completed';

        // Download signed contract PDF
        $opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Authorization: API-Key " . env('PANDADOC_KEY')
            )
        );
        $context = stream_context_create($opts);

        $imagePath = $proposal->band->site_name . '/' . $proposal->name . '_signed_contract_' . time() . '.pdf';

        Storage::disk('s3')->put(
            $imagePath,
            file_get_contents('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id . '/download', false, $context),
            ['visibility' => 'public']
        );
        $contract->image_url = Storage::disk('s3')->url($imagePath);

        // Notify band owners
        foreach ($proposal->band->owners as $owner) {
            $user = User::find($owner->user_id);
            $user->notify(new TTSNotification([
                'text' => 'Contract for ' . $proposal->name . ' signed and completed!',
                'route' => 'proposals',
                'routeParams' => '',
                'url' => '/proposals/'
            ]));
        }

        $contract->save();

        // Write to calendar
        $proposalService = new ProposalServices($proposal);
        $proposalService->writeToCalendar();

        $this->info("Processed contract for proposal: {$proposal->name}");

        Log::info('Contract completed and processed', [
            'contract_id' => $contract->id,
            'proposal_id' => $proposal->id,
            'proposal_name' => $proposal->name
        ]);
    }
}
