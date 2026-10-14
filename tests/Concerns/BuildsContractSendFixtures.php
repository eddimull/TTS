<?php

namespace Tests\Concerns;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\User;
use App\Services\PdfGeneratorService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Fixtures for driving the "send contract" endpoints end to end without
 * touching PandaDoc, S3 or headless Chromium. The stubbed PDF generator keeps
 * the rendered contract HTML in $renderedContractHtml so tests can assert on
 * the signature tags that went into the PDF.
 */
trait BuildsContractSendFixtures
{
    /** @var \ArrayObject<int, string> rendered contract HTML, one entry per generated PDF */
    protected \ArrayObject $renderedContractHtml;

    protected function setUpContractSending(): void
    {
        Config::set('services.pandadoc.api_key', 'fake-api-key');
        Storage::fake('s3');

        $captured = $this->renderedContractHtml = new \ArrayObject();

        $this->app->bind(PdfGeneratorService::class, function () use ($captured) {
            return new class($captured) extends PdfGeneratorService {
                public function __construct(private \ArrayObject $captured) {}

                public function generateFromHtml(string $html, string $format = 'Legal', bool $taggedPdf = false): string
                {
                    $this->captured->append($html);

                    return '%PDF-1.4 fake contract bytes';
                }
            };
        });

        Http::fake([
            'api.pandadoc.com/*' => Http::response([
                'id'     => 'fake-pandadoc-id',
                'status' => 'document.draft',
            ], 201),
        ]);
    }

    /**
     * @return array{0: User, 1: Bands, 2: Bookings}
     */
    protected function makeSendableBooking(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create([
            'address' => '123 Main',
            'city'    => 'New Orleans',
            'state'   => 'LA',
            'zip'     => '70112',
            'logo'    => '/images/logo.png',
        ]);
        $band->owners()->create(['user_id' => $user->id]);
        Storage::disk('s3')->put('logo.png', 'fake-png-bytes');

        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'status'          => 'draft',
            'contract_option' => 'default',
        ]);
        $booking->contract()->create([
            'author_id'    => $user->id,
            'status'       => 'pending',
            'custom_terms' => [],
        ]);

        return [$user, $band, $booking];
    }

    protected function attachContact(Bookings $booking, string $name, bool $primary = false): Contacts
    {
        $contact = Contacts::factory()->create([
            'band_id' => $booking->band_id,
            'name'    => $name,
        ]);
        $booking->contacts()->attach($contact, ['role' => $primary ? 'Primary' : 'Contact', 'is_primary' => $primary]);

        return $contact;
    }

    protected function pandaDocCreateRequest(): ?Request
    {
        return collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->first(fn (Request $request) => $request->method() === 'POST'
                && $request->url() === 'https://api.pandadoc.com/public/v1/documents');
    }

    protected function pandaDocRecipients(): Collection
    {
        return collect($this->pandaDocCreateRequest()?->data()['recipients'] ?? []);
    }
}
