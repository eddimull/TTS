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
        $result = $contract->sendToPandaDoc($contact);

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

    public function test_cc_contacts_can_be_sent_to_pandadoc()
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
        $ccContact1 = Contacts::factory()->create();
        $ccContact2 = Contacts::factory()->create();
        $booking->contacts()->attach($contact, ['role' => 'Primary', 'is_primary' => true]);
        $booking->contacts()->attach($ccContact1, ['role' => 'CC']);
        $booking->contacts()->attach($ccContact2, ['role' => 'CC']);

        $contract = Contracts::factory()->for($booking, 'contractable')->create([
            'asset_url' => 'https://example.com/fake-contract.pdf',
            'custom_terms' => [
                ['title' => 'Term 1', 'content' => 'Content 1'],
                ['title' => 'Term 2', 'content' => 'Content 2'],
            ],
        ]);

        // Send the contract to PandaDoc
        $result = $contract->sendToPandaDoc($contact, $booking->contacts()->find([$ccContact1->id, $ccContact2->id]));

        // Assert that the HTTP request was sent with the correct data
        Http::assertSent(function ($request) use ($booking, $contact, $ccContact1, $ccContact2)
        {
            return $request->url() == 'https://api.pandadoc.com/public/v1/documents' &&
                $request['name'] == "Contract for {$booking->name} - {$booking->band->name}" &&
                $request['url'] == 'https://example.com/fake-contract.pdf' &&
                $request['recipients'][0]['email'] == $contact->email &&
                $request['recipients'][1]['email'] == $ccContact1->email &&
                $request['recipients'][2]['email'] == $ccContact2->email;
        });

        // Assert that the contract was updated with the PandaDoc ID and status
        $this->assertEquals('fake-pandadoc-id', $contract->fresh()->envelope_id);
        $this->assertEquals('sent', $contract->fresh()->status);

        // Assert that the result contains the expected data
        $this->assertEquals('fake-pandadoc-id', $result['id']);
        $this->assertEquals('document.draft', $result['status']);
    }



    public function test_single_cc_id_is_coerced_to_collection_for_pandadoc()
    {
        // Regression for TTS-BAND-158: the mobile sendContract controller
        // previously passed a single Contacts model (from ->find($id)) to
        // sendToPandaDoc, which type-hints ?Eloquent\Collection and blew up
        // with a TypeError. Resolving a single cc_id via ->whereKey()->get()
        // must yield an Eloquent Collection that sendToPandaDoc accepts.
        Http::fake([
            'api.pandadoc.com/*' => Http::response([
                'id' => 'fake-pandadoc-id',
                'status' => 'document.draft',
            ], 201)
        ]);

        $band = Bands::factory()->create();
        $booking = Bookings::factory()->forBand($band)->create();
        $contact = Contacts::factory()->create();
        $ccContact = Contacts::factory()->create();
        $booking->contacts()->attach($contact, ['role' => 'Primary', 'is_primary' => true]);
        $booking->contacts()->attach($ccContact, ['role' => 'CC']);

        $contract = Contracts::factory()->for($booking, 'contractable')->create([
            'asset_url' => 'https://example.com/fake-contract.pdf',
            'custom_terms' => [
                ['title' => 'Term 1', 'content' => 'Content 1'],
            ],
        ]);

        // Reproduce exactly how BookingsController::sendContract resolves a
        // single cc_id into the argument passed to sendToPandaDoc.
        $ccContacts = $booking->contacts()->whereKey($ccContact->id)->get();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $ccContacts);

        $result = $contract->sendToPandaDoc($contact, $ccContacts);

        Http::assertSent(function ($request) use ($contact, $ccContact)
        {
            return $request->url() == 'https://api.pandadoc.com/public/v1/documents' &&
                $request['recipients'][0]['email'] == $contact->email &&
                $request['recipients'][1]['email'] == $ccContact->email;
        });

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

        $contract->sendToPandaDoc($contact);

        // Assert that the contract status was not updated
        $this->assertNotEquals('sent', $contract->fresh()->status);
    }
}
