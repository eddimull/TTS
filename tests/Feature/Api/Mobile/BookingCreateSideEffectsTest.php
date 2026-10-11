<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\EventTypes;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Characterizes what mobile booking create and add-contact write besides the
 * booking row: contract with initial terms, templated event additional_data,
 * default roster, event values, and band-scoped contact find-or-create.
 * The band developer API / MCP share this creation path, so these pin it.
 */
class BookingCreateSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->user->id]);
        $this->token = $this->user->createToken('test-device')->plainTextToken;
    }

    private function mobile(): static
    {
        return $this->withToken($this->token)->withHeaders(['X-Band-ID' => $this->band->id]);
    }

    public function test_store_creates_contract_roster_timeline_and_event_values(): void
    {
        $roster = Roster::factory()->default()->create(['band_id' => $this->band->id]);
        $type = EventTypes::factory()->create();

        $response = $this->mobile()->postJson("/api/mobile/bands/{$this->band->id}/bookings", [
            'name' => 'Two nights',
            'event_type_id' => $type->id,
            'price' => 3000,
            'contract_option' => 'default',
            'events' => [
                ['title' => 'Night 1', 'date' => '2030-03-01', 'start_time' => '20:00', 'end_time' => '23:00'],
                ['title' => 'Night 2', 'date' => '2030-03-02', 'start_time' => '20:00'],
            ],
        ])->assertCreated();

        $booking = Bookings::findOrFail($response->json('booking.id'));
        $this->assertSame('draft', $booking->status);
        $this->assertSame($this->user->id, $booking->author_id);

        $contract = $booking->contract;
        $this->assertNotNull($contract);
        $this->assertSame('pending', $contract->status);
        $this->assertSame($this->user->id, $contract->author_id);
        $this->assertSame(
            json_decode(file_get_contents(storage_path('app/contract/InitialTerms.json')), true),
            $contract->custom_terms
        );
        $this->assertNotEmpty($contract->custom_terms);

        $events = $booking->events()->orderBy('date')->get();
        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertSame($roster->id, $event->roster_id);
            $this->assertSame('1500.00', $event->value);
            $this->assertSame(
                ['Load In', 'Soundcheck', 'Quiet'],
                collect($event->additional_data->times)->pluck('title')->all()
            );
            $this->assertTrue($event->additional_data->public);
            $this->assertFalse(isset($event->additional_data->wedding));
        }
        $this->assertSame('2030-03-01 16:00', $events[0]->additional_data->times[0]->time);
    }

    public function test_store_with_contract_none_is_confirmed(): void
    {
        $type = EventTypes::factory()->create();

        $response = $this->mobile()->postJson("/api/mobile/bands/{$this->band->id}/bookings", [
            'name' => 'No contract',
            'event_type_id' => $type->id,
            'price' => 500,
            'contract_option' => 'none',
            'events' => [['title' => 'Gig', 'date' => '2030-03-01', 'start_time' => '20:00']],
        ])->assertCreated();

        $this->assertSame('confirmed', Bookings::findOrFail($response->json('booking.id'))->status);
    }

    public function test_store_contact_reuses_band_contact_by_email_and_sets_primary(): void
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $existing = Contacts::factory()->create(['band_id' => $this->band->id, 'email' => 'jane@example.com', 'name' => 'Jane']);
        $otherBand = Bands::factory()->create();
        $foreign = Contacts::factory()->create(['band_id' => $otherBand->id, 'email' => 'bob@example.com']);

        $this->mobile()->postJson("/api/mobile/bands/{$this->band->id}/bookings/{$booking->id}/contacts", [
            'name' => 'Jane Renamed',
            'email' => 'jane@example.com',
            'is_primary' => true,
        ])->assertOk()->assertJsonCount(1, 'contacts');

        $this->assertSame(1, Contacts::where('email', 'jane@example.com')->count());
        $this->assertSame('Jane', $existing->fresh()->name);
        $this->assertTrue((bool) $booking->contacts()->first()->pivot->is_primary);

        // Same email in another band is not reused: a new band contact is made.
        $this->mobile()->postJson("/api/mobile/bands/{$this->band->id}/bookings/{$booking->id}/contacts", [
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ])->assertOk()->assertJsonCount(2, 'contacts');

        $bob = Contacts::where('band_id', $this->band->id)->where('email', 'bob@example.com')->firstOrFail();
        $this->assertNotSame($foreign->id, $bob->id);
        $this->assertFalse((bool) $booking->contacts()->where('contacts.id', $bob->id)->first()->pivot->is_primary);
    }

    public function test_store_contact_by_id_rejects_another_bands_contact(): void
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $foreign = Contacts::factory()->create(['band_id' => Bands::factory()->create()->id]);

        $this->mobile()->postJson("/api/mobile/bands/{$this->band->id}/bookings/{$booking->id}/contacts", [
            'contact_id' => $foreign->id,
        ])->assertNotFound();

        $this->assertSame(0, $booking->contacts()->count());
    }
}
