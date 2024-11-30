<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use App\Models\Contacts;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class ContractSendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set a fake API key for testing
        Config::set('services.pandadoc.api_key', 'fake-api-key');
    }

    public function test_contract_can_be_sent_to_pandadoc()
    {
        // Mock the PandaDoc API response
        Http::fake([
            'api.pandadoc.com/*' => Http::response([
                'id' => 'fake-pandadoc-id',
                'status' => 'document.draft',
            ], 201)
        ]);

        // Create a band, booking, and contract
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->forBand($band)->create();
        $contact = Contacts::factory()->create();
        $booking->contacts()->attach($contact, ['role' => 'Primary', 'is_primary' => true]);

        $contract = Contracts::factory()->for($booking, 'contractable')->create([
            'asset_url' => 'https://example.com/fake-contract.pdf',
            'custom_terms' => [
                ['title' => 'Term 1', 'content' => 'Content 1'],
                ['title' => 'Term 2', 'content' => 'Content 2'],
            ],
        ]);

        // Send the contract to PandaDoc
        $result = $contract->sendToPandaDoc();

        // Assert that the HTTP request was sent with the correct data
        Http::assertSent(function ($request) use ($booking, $contact)
        {
            return $request->url() == 'https://api.pandadoc.com/public/v1/documents' &&
                $request['name'] == "Contract for {$booking->name} - {$booking->band->name}" &&
                $request['url'] == 'https://example.com/fake-contract.pdf' &&
                $request['recipients'][0]['email'] == $contact->email;
        });

        // Assert that the contract was updated with the PandaDoc ID and status
        $this->assertEquals('fake-pandadoc-id', $contract->fresh()->envelope_id);
        $this->assertEquals('sent', $contract->fresh()->status);

        // Assert that the result contains the expected data
        $this->assertEquals('fake-pandadoc-id', $result['id']);
        $this->assertEquals('document.draft', $result['status']);
    }

    public function test_contract_sending_handles_api_error()
    {
        // Override the fake to simulate an API error
        Http::fake([
            'api.pandadoc.com/*' => Http::response(['error' => 'API Error'], 400)
        ]);

        $band = Bands::factory()->create();
        $booking = Bookings::factory()->forBand($band)->create();
        $contact = Contacts::factory()->create();
        $booking->contacts()->attach($contact, ['role' => 'Primary', 'is_primary' => true]);

        $contract = Contracts::factory()->for($booking, 'contractable')->create([
            'asset_url' => 'https://example.com/fake-contract.pdf',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to send document to PandaDoc');

        $contract->sendToPandaDoc();

        // Assert that the contract status was not updated
        $this->assertNotEquals('sent', $contract->fresh()->status);
    }
}
