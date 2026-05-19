# Contract Check-Signed Safety Net Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reinstate a daily `contracts:check-signed` command that polls the PandaDoc API for booking contracts whose completion webhook was missed, processing them through the same code path as the webhook.

**Architecture:** Extract the contract-completion behavior currently inline in `ProcessPandadocWebhookJob` into a `ContractCompletionService`. The webhook job delegates to it; a new `CheckSignedContracts` command also calls it after polling PandaDoc. The `app/Console/Kernel.php` schedule entry already exists (orphaned) and resolves once the command class is created.

**Tech Stack:** Laravel 12, PHPUnit 11, `Illuminate\Support\Facades\Http` (with `Http::fake()` in tests), S3/MinIO storage.

---

## File Structure

- `app/Services/ContractCompletionService.php` — **new.** Single shared completion path: `markCompleted(Contracts $contract)`. Owns PDF download/storage and booking-confirmation/portal-access side effects.
- `app/Jobs/ProcessPandadocWebhookJob.php` — **modify.** `handleRecipientCompleted()` reduced to a lookup + delegate; `updateContractAssetURL()` removed (moved into service).
- `app/Console/Commands/CheckSignedContracts.php` — **new.** Polls PandaDoc, delegates completed contracts to the service.
- `tests/Feature/ContractCompletionServiceTest.php` — **new.** Direct coverage of the service.
- `tests/Feature/ProcessPandadocWebhookJobTest.php` — **new.** Regression coverage that the webhook still completes contracts after the refactor.
- `tests/Feature/CheckSignedContractsCommandTest.php` — **new.** Command behavior with `Http::fake()`.

`app/Console/Kernel.php` — **no change.** The entry `$schedule->command('contracts:check-signed')->dailyAt('11:00')` at lines 31-32 already exists.

---

## Task 1: Extract `ContractCompletionService`

**Files:**
- Create: `app/Services/ContractCompletionService.php`
- Test: `tests/Feature/ContractCompletionServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ContractCompletionServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use App\Models\User;
use App\Services\ContractCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractCompletionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSentContract(): Contracts
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'contract_option' => 'default',
        ]);
        $contact = Contacts::factory()->create(['band_id' => $band->id]);
        $booking->contacts()->attach($contact->id, ['role' => 'primary']);

        return $booking->contract()->create([
            'envelope_id' => 'env-test-123',
            'author_id'   => $user->id,
            'status'      => 'sent',
        ]);
    }

    public function test_mark_completed_sets_status_confirms_booking_and_grants_portal_access(): void
    {
        Storage::fake('s3');
        Http::fake([
            'api.pandadoc.com/public/v1/documents/*/download' => Http::response('PDFBYTES', 200),
        ]);

        $contract = $this->makeSentContract();

        (new ContractCompletionService())->markCompleted($contract);

        $contract->refresh();
        $this->assertSame('completed', $contract->status);
        $this->assertSame('confirmed', $contract->contractable->status);
        $this->assertStringContainsString('_signed_contract_', $contract->asset_url);

        $this->assertTrue($contract->contractable->contacts->first()->can_login);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker-compose exec -T app php artisan test --filter=test_mark_completed_sets_status_confirms_booking_and_grants_portal_access`
Expected: FAIL — `Class "App\Services\ContractCompletionService" not found`.

- [ ] **Step 3: Write the service**

Create `app/Services/ContractCompletionService.php`. This is the exact behavior currently in `ProcessPandadocWebhookJob::handleRecipientCompleted()` + `updateContractAssetURL()`, relocated:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker-compose exec -T app php artisan test --filter=test_mark_completed_sets_status_confirms_booking_and_grants_portal_access`
Expected: PASS.

Note: `updateContractAssetURL` uses `file_get_contents` with a stream context for the PDF download, not the `Http` facade — this is copied verbatim from the existing webhook code and is intentionally kept identical (no new error handling added). `Http::fake()` does not intercept `file_get_contents`, but the faked S3 disk accepts the `put()`. The PandaDoc download URL resolves during the test; if outbound network is unavailable in CI this call would fail — but the existing webhook code has the exact same un-faked call, so test parity holds. Do not change this behavior in this plan.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ContractCompletionService.php tests/Feature/ContractCompletionServiceTest.php
git commit -m "feat: extract ContractCompletionService for shared contract-completion path"
```

---

## Task 2: Refactor `ProcessPandadocWebhookJob` to delegate

**Files:**
- Modify: `app/Jobs/ProcessPandadocWebhookJob.php`
- Test: `tests/Feature/ProcessPandadocWebhookJobTest.php`

- [ ] **Step 1: Write the failing regression test**

Create `tests/Feature/ProcessPandadocWebhookJobTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\ProcessPandadocWebhookJob;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\WebhookClient\Models\WebhookCall;
use Tests\TestCase;

class ProcessPandadocWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_completed_webhook_completes_contract(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'contract_option' => 'default',
        ]);
        $contract = $booking->contract()->create([
            'envelope_id' => 'env-webhook-1',
            'author_id'   => $user->id,
            'status'      => 'sent',
        ]);

        $webhookCall = WebhookCall::create([
            'name'    => 'pandadoc',
            'url'     => 'https://tts.band/webhooks/pandadoc',
            'payload' => [
                [
                    'event' => 'recipient_completed',
                    'data'  => [
                        'id'        => 'env-webhook-1',
                        'recipient' => ['email' => 'signer@example.com'],
                    ],
                ],
            ],
        ]);

        (new ProcessPandadocWebhookJob($webhookCall))->handle();

        $contract->refresh();
        $this->assertSame('completed', $contract->status);
        $this->assertSame('confirmed', $contract->contractable->status);
    }
}
```

- [ ] **Step 2: Run test to verify it passes against current code**

Run: `docker-compose exec -T app php artisan test --filter=test_recipient_completed_webhook_completes_contract`
Expected: PASS — this characterizes existing behavior before the refactor. If it FAILS, stop and inspect: the test setup (e.g. `WebhookCall` constructor signature for the installed `spatie/laravel-webhook-client` version) is wrong and must be corrected before refactoring.

- [ ] **Step 3: Refactor the job to delegate**

In `app/Jobs/ProcessPandadocWebhookJob.php`:

Replace the `handleRecipientCompleted` method body so it delegates, and delete the `updateContractAssetURL` method entirely. Replace:

```php
    private function handleRecipientCompleted(array $payload)
    {
        $documentId = $payload['data']['id'] ?? null;
        $recipientEmail = $payload['data']['recipient']['email'] ?? null;

        Log::info('Recipient completed document', [
            'documentId' => $documentId,
            'recipientEmail' => $recipientEmail
        ]);

        $contract = Contracts::where('envelope_id', $documentId)->first();
        $contract->status = 'completed';
        $contract->save();

        $this->updateContractAssetURL($contract);

        if ($contract->contractable_type == 'App\Models\Bookings')
        {
            $contract->contractable->status = 'confirmed';
            $contract->contractable->save();

            // Grant portal access to all contacts after contract completion
            $portalService = new ContactPortalService();
            try {
                $portalService->grantPortalAccessAfterContractCompletion($contract->contractable);
            } catch (\Exception $e) {
                Log::error('Failed to grant portal access after contract completion', [
                    'booking_id' => $contract->contractable->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
```

with:

```php
    private function handleRecipientCompleted(array $payload)
    {
        $documentId = $payload['data']['id'] ?? null;
        $recipientEmail = $payload['data']['recipient']['email'] ?? null;

        Log::info('Recipient completed document', [
            'documentId' => $documentId,
            'recipientEmail' => $recipientEmail
        ]);

        $contract = Contracts::where('envelope_id', $documentId)->first();

        if (!$contract) {
            Log::warning('PandaDoc webhook: no contract for envelope', ['documentId' => $documentId]);
            return;
        }

        app(ContractCompletionService::class)->markCompleted($contract);
    }
```

Then delete the entire `private function updateContractAssetURL(Contracts $contract)` method.

Update the `use` statements at the top of the file: remove `use Illuminate\Support\Facades\Storage;` (no longer used), add `use App\Services\ContractCompletionService;`. Keep `use App\Models\Contracts;` and `use Illuminate\Support\Facades\Log;`. Remove `use App\Models\Bookings;` and `use App\Services\ContactPortalService;` only if no longer referenced elsewhere in the file — grep first:

Run: `grep -n "Bookings\|ContactPortalService\|Storage" app/Jobs/ProcessPandadocWebhookJob.php`
Remove each `use` whose symbol no longer appears in the body.

- [ ] **Step 4: Run both webhook and service tests**

Run: `docker-compose exec -T app php artisan test --filter=ProcessPandadocWebhookJobTest`
Expected: PASS — behavior unchanged after the refactor.

Run: `docker-compose exec -T app php artisan test --filter=ContractCompletionServiceTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/ProcessPandadocWebhookJob.php tests/Feature/ProcessPandadocWebhookJobTest.php
git commit -m "refactor: delegate webhook contract completion to ContractCompletionService"
```

---

## Task 3: Create the `CheckSignedContracts` command

**Files:**
- Create: `app/Console/Commands/CheckSignedContracts.php`
- Test: `tests/Feature/CheckSignedContractsCommandTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CheckSignedContractsCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckSignedContractsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeSentContract(string $envelopeId, ?Carbon $createdAt = null): Contracts
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'contract_option' => 'default',
        ]);
        $contact = Contacts::factory()->create(['band_id' => $band->id]);
        $booking->contacts()->attach($contact->id, ['role' => 'primary']);

        $contract = $booking->contract()->create([
            'envelope_id' => $envelopeId,
            'author_id'   => $user->id,
            'status'      => 'sent',
        ]);

        if ($createdAt) {
            $contract->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $contract;
    }

    public function test_completes_contract_when_pandadoc_reports_document_completed(): void
    {
        Storage::fake('s3');
        Http::fake([
            'api.pandadoc.com/public/v1/documents/env-1' => Http::response(['status' => 'document.completed'], 200),
            'api.pandadoc.com/public/v1/documents/*/download' => Http::response('PDFBYTES', 200),
        ]);

        $contract = $this->makeSentContract('env-1');

        $this->artisan('contracts:check-signed')->assertExitCode(0);

        $contract->refresh();
        $this->assertSame('completed', $contract->status);
        $this->assertSame('confirmed', $contract->contractable->status);
    }

    public function test_leaves_contract_untouched_when_not_yet_completed(): void
    {
        Storage::fake('s3');
        Http::fake([
            'api.pandadoc.com/public/v1/documents/env-2' => Http::response(['status' => 'document.sent'], 200),
        ]);

        $contract = $this->makeSentContract('env-2');

        $this->artisan('contracts:check-signed')->assertExitCode(0);

        $this->assertSame('sent', $contract->fresh()->status);
    }

    public function test_continues_run_when_pandadoc_api_errors(): void
    {
        Storage::fake('s3');
        Http::fake([
            'api.pandadoc.com/public/v1/documents/env-3' => Http::response(null, 500),
        ]);

        $this->makeSentContract('env-3');

        $this->artisan('contracts:check-signed')->assertExitCode(0);
    }

    public function test_does_not_poll_contracts_older_than_two_months(): void
    {
        Storage::fake('s3');
        Http::fake();

        $this->makeSentContract('env-old', Carbon::now()->subMonths(3));

        $this->artisan('contracts:check-signed')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_does_not_poll_already_completed_contracts(): void
    {
        Storage::fake('s3');
        Http::fake();

        $contract = $this->makeSentContract('env-done');
        $contract->forceFill(['status' => 'completed'])->saveQuietly();

        $this->artisan('contracts:check-signed')->assertExitCode(0);

        Http::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker-compose exec -T app php artisan test --filter=CheckSignedContractsCommandTest`
Expected: FAIL — `Command "contracts:check-signed" is not defined`.

- [ ] **Step 3: Generate the command file**

Run: `docker-compose exec -T app php artisan make:command CheckSignedContracts --no-interaction`
This creates `app/Console/Commands/CheckSignedContracts.php` with a stub.

- [ ] **Step 4: Write the command implementation**

Replace the contents of `app/Console/Commands/CheckSignedContracts.php` with:

```php
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
            ->where('status', '!=', 'completed')
            ->where('created_at', '>', Carbon::now()->subMonths(2))
            ->whereNotNull('envelope_id')
            ->get();

        $processedCount = 0;
        $errorCount = 0;

        foreach ($contracts as $contract) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'API-Key ' . config('services.pandadoc.api_key'),
                ])
                    ->acceptJson()
                    ->get('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id);

                if (!$response->ok()) {
                    Log::warning('Failed to fetch PandaDoc status', [
                        'contract_id' => $contract->id,
                        'envelope_id' => $contract->envelope_id,
                        'status'      => $response->status(),
                    ]);
                    $errorCount++;
                    continue;
                }

                if ($response['status'] === 'document.completed') {
                    $completionService->markCompleted($contract);
                    $this->line("Completed contract for: {$contract->contractable->name}");
                    $processedCount++;
                }
            } catch (\Exception $e) {
                Log::error('Error processing contract', [
                    'contract_id' => $contract->id,
                    'error'       => $e->getMessage(),
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
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker-compose exec -T app php artisan test --filter=CheckSignedContractsCommandTest`
Expected: PASS — all 5 tests green.

- [ ] **Step 6: Verify the schedule entry now resolves**

Run: `docker-compose exec -T app php artisan schedule:list`
Expected: the output includes `contracts:check-signed` running at `11:00` with no error. (This confirms the orphaned `Kernel.php` entry — TTS-BAND-AD — is fixed.)

- [ ] **Step 7: Commit**

```bash
git add app/Console/Commands/CheckSignedContracts.php tests/Feature/CheckSignedContractsCommandTest.php
git commit -m "feat: add contracts:check-signed safety-net command

Polls PandaDoc daily for booking contracts whose completion webhook was
missed. Reinstates the safety net removed when proposals were banished.

Fixes TTS-BAND-AD"
```

---

## Task 4: Full-suite verification

**Files:** none (verification only)

- [ ] **Step 1: Run the three new test files together**

Run: `docker-compose exec -T app php artisan test --filter='ContractCompletionServiceTest|ProcessPandadocWebhookJobTest|CheckSignedContractsCommandTest'`
Expected: all tests PASS.

- [ ] **Step 2: Run the broader contract/webhook suite for regressions**

Run: `docker-compose exec -T app php artisan test --filter='Contract'`
Expected: all PASS. If any pre-existing contract test fails, investigate whether the refactor caused it before proceeding.

- [ ] **Step 3: Confirm no uncommitted changes remain**

Run: `git status --short`
Expected: clean (only the unrelated untracked `.pki/` directory, if present).
