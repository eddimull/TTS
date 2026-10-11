<?php

namespace Tests\Feature;

use App\Exceptions\PandaDocVoidException;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use App\Services\ContractAmendmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ContractAmendmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.pandadoc.api_key', 'fake-api-key');
    }

    /** Booking in the amendable state: default option, contract sent, booking pending. */
    private function amendableBooking(): Bookings
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'status'          => 'pending',
            'contract_option' => 'default',
        ]);
        $booking->contract()->create([
            'author_id'   => $user->id,
            'status'      => 'sent',
            'envelope_id' => 'pd-doc-123',
            'custom_terms' => [['title' => 'T', 'content' => 'C']],
        ]);

        return $booking->fresh();
    }

    public function test_amend_voids_document_and_resets_state(): void
    {
        Http::fake(['api.pandadoc.com/*' => Http::response([], 200)]);

        $booking = $this->amendableBooking();
        app(ContractAmendmentService::class)->amend($booking);

        Http::assertSent(fn ($req) =>
            $req->url() === 'https://api.pandadoc.com/public/v1/documents/pd-doc-123/status'
            && $req->method() === 'PATCH'
            && ($req['status'] ?? null) === 11
        );

        $booking->refresh();
        $this->assertSame('draft', $booking->status);
        $this->assertSame('pending', $booking->contract->status);
        $this->assertNull($booking->contract->envelope_id);
        $this->assertNotEmpty($booking->contract->custom_terms);
    }

    public function test_amend_tolerates_document_already_deleted(): void
    {
        Http::fake(['api.pandadoc.com/*' => Http::response(['detail' => 'Not found'], 404)]);

        $booking = $this->amendableBooking();
        app(ContractAmendmentService::class)->amend($booking);

        $this->assertSame('draft', $booking->fresh()->status);
    }

    public function test_amend_aborts_without_db_changes_when_void_fails(): void
    {
        Http::fake(['api.pandadoc.com/*' => Http::response(['detail' => 'boom'], 500)]);

        $booking = $this->amendableBooking();

        try {
            app(ContractAmendmentService::class)->amend($booking);
            $this->fail('Expected exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('void', strtolower($e->getMessage()));
        }

        $booking->refresh();
        $this->assertSame('pending', $booking->status);
        $this->assertSame('sent', $booking->contract->status);
        $this->assertSame('pd-doc-123', $booking->contract->envelope_id);
    }

    /** Void PATCH answers $voidStatus; the document status GET reports $docStatus. */
    private function fakeVoidRefused(string $docStatus, int $voidStatus = 400): void
    {
        Http::fake(function ($request) use ($docStatus, $voidStatus) {
            if ($request->method() === 'PATCH') {
                return Http::response(['detail' => 'Cannot change status'], $voidStatus);
            }

            return Http::response(['id' => 'pd-doc-123', 'status' => $docStatus], 200);
        });
    }

    public function test_amend_proceeds_when_document_already_voided(): void
    {
        $this->fakeVoidRefused('document.voided');

        $booking = $this->amendableBooking();
        app(ContractAmendmentService::class)->amend($booking);

        $booking->refresh();
        $this->assertSame('draft', $booking->status);
        $this->assertSame('pending', $booking->contract->status);
        $this->assertNull($booking->contract->envelope_id);
    }

    public function test_amend_proceeds_when_document_declined(): void
    {
        $this->fakeVoidRefused('document.declined');

        $booking = $this->amendableBooking();
        app(ContractAmendmentService::class)->amend($booking);

        $this->assertSame('draft', $booking->fresh()->status);
    }

    public function test_amend_rejects_document_already_signed_in_pandadoc(): void
    {
        $this->fakeVoidRefused('document.completed');

        $booking = $this->amendableBooking();

        try {
            app(ContractAmendmentService::class)->amend($booking);
            $this->fail('Expected exception');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('signed', $e->getMessage());
        }

        $booking->refresh();
        $this->assertSame('pending', $booking->status);
        $this->assertSame('pd-doc-123', $booking->contract->envelope_id);
    }

    public function test_amend_throws_void_exception_when_document_still_signable(): void
    {
        $this->fakeVoidRefused('document.sent');

        $booking = $this->amendableBooking();

        $this->expectException(PandaDocVoidException::class);
        app(ContractAmendmentService::class)->amend($booking);
    }

    public function test_amend_rejects_external_option(): void
    {
        Http::fake();
        $booking = $this->amendableBooking();
        $booking->update(['contract_option' => 'external']);

        $this->expectException(InvalidArgumentException::class);
        app(ContractAmendmentService::class)->amend($booking->fresh());
    }

    public function test_amend_rejects_unsent_contract(): void
    {
        Http::fake();
        $booking = $this->amendableBooking();
        $booking->contract->update(['status' => 'pending']);

        $this->expectException(InvalidArgumentException::class);
        app(ContractAmendmentService::class)->amend($booking->fresh());
    }

    public function test_amend_rejects_completed_contract(): void
    {
        Http::fake();
        $booking = $this->amendableBooking();
        $booking->contract->update(['status' => 'completed']);
        $booking->update(['status' => 'confirmed']);

        $this->expectException(InvalidArgumentException::class);
        app(ContractAmendmentService::class)->amend($booking->fresh());
    }
}
