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
