<?php

namespace Tests\Feature;

use App\Models\BandApiToken;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\EventTypes;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * POST /api/bookings creates bookings the way mobile/web do (shared
 * BookingService::createBooking), plus client contacts and
 * POST /api/bookings/{id}/contacts.
 */
class BandApiBookingCreateParityTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Roster $roster;
    private EventTypes $wedding;
    private EventTypes $barGig;
    private string $writeToken;
    private string $readToken;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->artisan('db:seed', ['--class' => 'ApiPermissionsSeeder']);

        $this->owner = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->roster = Roster::factory()->default()->create(['band_id' => $this->band->id]);

        // The event template keys wedding extras off event type id 1.
        $this->wedding = EventTypes::find(1) ?? EventTypes::factory()->create(['id' => 1, 'name' => 'Wedding']);
        $this->barGig = EventTypes::factory()->create(['name' => 'Bar Gig']);

        $this->writeToken = $this->token(['api:write-bookings']);
        $this->readToken = $this->token(['api:read-bookings']);
    }

    private function token(array $permissions, ?Bands $band = null): string
    {
        $plain = Str::random(60);
        BandApiToken::create([
            'band_id' => ($band ?? $this->band)->id,
            'token' => hash('sha256', $plain),
            'name' => 'Token ' . Str::random(4),
            'is_active' => true,
        ])->givePermissionTo($permissions);

        return $plain;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Lead Gig',
            'event_type_id' => $this->barGig->id,
            'date' => '2027-03-06',
            'start_time' => '20:00',
            'duration' => 3,
            'price' => 2400,
            'contract_option' => 'default',
            'venue_name' => 'Blue Moon',
        ], $overrides);
    }

    private function create(array $overrides = []): Bookings
    {
        $response = $this->withToken($this->writeToken)
            ->postJson('/api/bookings', $this->payload($overrides))
            ->assertCreated();

        return Bookings::findOrFail($response->json('booking.id'));
    }

    public function test_create_generates_contract_timeline_roster_and_event_value(): void
    {
        $booking = $this->create();

        $this->assertSame('draft', $booking->status);
        $this->assertSame($this->owner->id, $booking->author_id);

        $contract = $booking->contract;
        $this->assertNotNull($contract);
        $this->assertSame('pending', $contract->status);
        $this->assertSame($this->owner->id, $contract->author_id);
        $this->assertNotEmpty($contract->custom_terms);
        $this->assertSame(
            json_decode(file_get_contents(storage_path('app/contract/InitialTerms.json')), true),
            $contract->custom_terms
        );

        $event = $booking->events()->sole();
        $this->assertSame('Lead Gig', $event->title);
        $this->assertSame('23:00', $event->end_time->format('H:i'));
        $this->assertSame($this->roster->id, $event->roster_id);
        $this->assertSame('2400.00', $event->value);
        $this->assertSame($this->barGig->id, $event->event_type_id);

        $data = $event->additional_data;
        $this->assertSame(
            [['Load In', '2027-03-06 16:00'], ['Soundcheck', '2027-03-06 17:00'], ['Quiet', '2027-03-06 19:00']],
            collect($data->times)->map(fn ($t) => [$t->title, $t->time])->all()
        );
        $this->assertFalse($data->backline_provided);
        $this->assertTrue($data->production_needed);
        $this->assertTrue($data->public);
        $this->assertFalse($data->outside);
        $this->assertCount(4, $data->lodging);
        $this->assertFalse(isset($data->wedding));
    }

    public function test_wedding_create_adds_ceremony_dances_and_is_private(): void
    {
        $booking = $this->create(['event_type_id' => $this->wedding->id, 'start_time' => '18:00']);

        $data = $booking->events()->sole()->additional_data;
        $this->assertContains(['Ceremony', '2027-03-06 18:00'], collect($data->times)->map(fn ($t) => [$t->title, $t->time])->all());
        $this->assertTrue($data->wedding->onsite);
        $this->assertSame(
            ['first_dance', 'father_daughter', 'mother_son', 'money_dance', 'bouquet_garter'],
            collect($data->wedding->dances)->pluck('title')->all()
        );
        $this->assertTrue($data->onsite);
        $this->assertFalse($data->public);
    }

    public function test_status_is_not_derived_from_contract_option(): void
    {
        $this->assertSame('draft', $this->create(['contract_option' => 'none'])->status);
        $this->assertSame('pending', $this->create(['contract_option' => 'none', 'status' => 'pending'])->status);
    }

    public function test_create_with_new_contact_makes_it_primary(): void
    {
        $response = $this->withToken($this->writeToken)->postJson('/api/bookings', $this->payload([
            'contact' => ['name' => 'Jane Lead', 'email' => 'jane@example.com', 'phone' => '337-555-0100'],
        ]))->assertCreated();

        $response->assertJsonPath('booking.contacts.0.email', 'jane@example.com')
            ->assertJsonPath('booking.contacts.0.name', 'Jane Lead')
            ->assertJsonPath('booking.contacts.0.pivot.is_primary', 1);

        $contact = Contacts::where('email', 'jane@example.com')->sole();
        $this->assertSame($this->band->id, $contact->band_id);
        $this->assertSame('337-555-0100', $contact->phone);
    }

    public function test_create_reuses_the_bands_existing_contact_by_email(): void
    {
        $existing = Contacts::factory()->create(['band_id' => $this->band->id, 'email' => 'repeat@example.com', 'name' => 'Repeat Client']);
        $foreign = Contacts::factory()->create(['band_id' => Bands::factory()->create()->id, 'email' => 'planner@example.com']);

        $booking = $this->create([
            'contact' => ['name' => 'Someone Else', 'email' => 'repeat@example.com'],
            'contacts' => [['name' => 'Pat Planner', 'email' => 'planner@example.com', 'role' => 'Planner']],
        ]);

        $contacts = $booking->contacts()->get()->keyBy('email');
        $this->assertSame($existing->id, $contacts['repeat@example.com']->id);
        $this->assertSame('Repeat Client', $existing->fresh()->name);
        $this->assertTrue((bool) $contacts['repeat@example.com']->pivot->is_primary);

        // Another band's contact with the same email is never reused.
        $this->assertNotSame($foreign->id, $contacts['planner@example.com']->id);
        $this->assertSame($this->band->id, $contacts['planner@example.com']->band_id);
        $this->assertFalse((bool) $contacts['planner@example.com']->pivot->is_primary);
        $this->assertSame('Planner', $contacts['planner@example.com']->pivot->role);
    }

    public function test_create_contact_respects_explicit_is_primary_false(): void
    {
        $booking = $this->create(['contact' => ['name' => 'Cc', 'email' => 'cc@example.com', 'is_primary' => false]]);

        $this->assertFalse((bool) $booking->contacts()->sole()->pivot->is_primary);
    }

    public function test_create_validates_contact_and_writes_nothing(): void
    {
        $this->withToken($this->writeToken)
            ->postJson('/api/bookings', $this->payload([
                'contact' => ['name' => 'No Email'],
                'contacts' => [['email' => 'not-an-email']],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contact.email', 'contacts.0.name', 'contacts.0.email']);

        $this->assertSame(0, Bookings::count());
    }

    public function test_price_update_resplits_event_values(): void
    {
        $booking = $this->create();

        $this->withToken($this->writeToken)->patchJson("/api/bookings/{$booking->id}", ['price' => 3000])->assertOk();

        $this->assertSame('3000.00', $booking->events()->sole()->value);
    }

    public function test_add_contact_endpoint(): void
    {
        $booking = $this->create(['contact' => ['name' => 'Jane', 'email' => 'jane@example.com']]);

        $response = $this->withToken($this->writeToken)
            ->postJson("/api/bookings/{$booking->id}/contacts", [
                'name' => 'Mom',
                'email' => 'mom@example.com',
                'role' => 'Mother of the bride',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Contact added successfully')
            ->assertJsonPath('booking.id', $booking->id)
            ->assertJsonCount(2, 'booking.contacts');

        $mom = collect($response->json('booking.contacts'))->firstWhere('email', 'mom@example.com');
        $this->assertSame(0, $mom['pivot']['is_primary'], 'booking already has a primary contact');
        $this->assertSame('Mother of the bride', $mom['pivot']['role']);

        // Re-adding updates the pivot instead of duplicating.
        $this->withToken($this->writeToken)
            ->postJson("/api/bookings/{$booking->id}/contacts", ['name' => 'Mom', 'email' => 'mom@example.com', 'is_primary' => true])
            ->assertCreated()
            ->assertJsonCount(2, 'booking.contacts');
        $this->assertTrue((bool) $booking->contacts()->where('email', 'mom@example.com')->sole()->pivot->is_primary);
    }

    public function test_add_contact_defaults_primary_when_booking_has_none(): void
    {
        $booking = $this->create();

        $this->withToken($this->writeToken)
            ->postJson("/api/bookings/{$booking->id}/contacts", ['name' => 'First', 'email' => 'first@example.com'])
            ->assertCreated()
            ->assertJsonPath('booking.contacts.0.pivot.is_primary', 1);
    }

    public function test_add_contact_validates(): void
    {
        $booking = $this->create();

        $this->withToken($this->writeToken)
            ->postJson("/api/bookings/{$booking->id}/contacts", ['name' => 'No Email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_add_contact_needs_write_permission(): void
    {
        $booking = $this->create();

        $this->withToken($this->readToken)
            ->postJson("/api/bookings/{$booking->id}/contacts", ['name' => 'X', 'email' => 'x@example.com'])
            ->assertForbidden();

        $this->flushHeaders()->postJson("/api/bookings/{$booking->id}/contacts", ['name' => 'X', 'email' => 'x@example.com'])
            ->assertUnauthorized();

        $this->assertSame(0, $booking->contacts()->count());
    }

    public function test_add_contact_to_another_bands_booking_is_not_found(): void
    {
        $otherBand = Bands::factory()->create();
        $other = Bookings::factory()->create(['band_id' => $otherBand->id]);

        $this->withToken($this->writeToken)
            ->postJson("/api/bookings/{$other->id}/contacts", ['name' => 'X', 'email' => 'x@example.com'])
            ->assertNotFound()
            ->assertExactJson(['error' => 'Not Found', 'message' => 'Booking not found or does not belong to your band']);

        $this->assertSame(0, $other->contacts()->count());
        $this->assertFalse(Contacts::where('email', 'x@example.com')->exists());
    }
}
