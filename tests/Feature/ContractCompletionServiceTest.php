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

    public function test_mark_completed_leaves_contract_unchanged_when_pdf_download_fails(): void
    {
        Storage::fake('s3');
        Http::fake([
            'api.pandadoc.com/public/v1/documents/*/download' => Http::response('', 500),
        ]);

        $contract = $this->makeSentContract();

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        try {
            (new ContractCompletionService())->markCompleted($contract);
        } finally {
            // Status must NOT have flipped — the download failed before any save.
            $this->assertSame('sent', $contract->fresh()->status);
        }
    }
}
