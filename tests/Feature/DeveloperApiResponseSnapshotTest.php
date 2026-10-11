<?php

namespace Tests\Feature;

use App\Models\BandApiToken;
use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Byte-for-byte characterization of the band developer API responses.
 *
 * The golden files in tests/Fixtures/developer-api were recorded from the
 * controllers before their query/format logic was extracted into
 * App\Services\BandApi (shared with the MCP server). Every row uses fixed
 * ids and a frozen clock so the bodies are deterministic; if one of these
 * assertions fails, a REST response changed shape — that is a breaking change
 * for API consumers, not a snapshot to regenerate casually.
 *
 * To (re)record after an intentional change: delete the fixture and run with
 * RECORD_SNAPSHOTS=1.
 */
class DeveloperApiResponseSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private const BAND_ID = 970001;
    private const OTHER_BAND_ID = 970002;

    private string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00'));

        $this->artisan('db:seed', ['--class' => 'ApiPermissionsSeeder']);

        $owner = User::factory()->create();
        $band = Bands::factory()->create(['id' => self::BAND_ID, 'name' => 'Snapshot Band']);
        $band->owners()->create(['user_id' => $owner->id]);
        Bands::factory()->create(['id' => self::OTHER_BAND_ID, 'name' => 'Other Band']);

        EventTypes::factory()->create(['id' => 970101, 'name' => 'Wedding']);
        EventTypes::factory()->create(['id' => 970102, 'name' => 'Bar Gig']);

        $this->plainToken = Str::random(60);
        BandApiToken::create([
            'band_id' => self::BAND_ID,
            'token' => hash('sha256', $this->plainToken),
            'name' => 'Snapshot Token',
            'is_active' => true,
        ])->givePermissionTo(['api:read-bookings', 'api:write-bookings', 'api:read-events', 'api:read-pricing']);

        $this->seedRows($owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedRows(User $owner): void
    {
        $wedding = Bookings::factory()->create([
            'id' => 970201,
            'band_id' => self::BAND_ID,
            'author_id' => $owner->id,
            'name' => 'Smith Wedding',
            'event_type_id' => 970101,
            'price' => 4500,
            'status' => 'confirmed',
            'contract_option' => 'default',
            'notes' => 'Ceremony + reception',
        ]);
        Events::factory()->create([
            'id' => 970301,
            'eventable_type' => Bookings::class,
            'eventable_id' => $wedding->id,
            'event_type_id' => 970101,
            'title' => 'Smith Wedding',
            'date' => '2026-11-14',
            'start_time' => '18:00',
            'end_time' => '23:00',
            'venue_name' => 'The Grand Hall',
            'venue_address' => '1 Main St, Lafayette, LA',
            'notes' => 'Ceremony at 5',
            'additional_data' => ['public' => true],
        ]);

        // Multi-event booking: two nights, inserted out of date order.
        $festival = Bookings::factory()->create([
            'id' => 970202,
            'band_id' => self::BAND_ID,
            'author_id' => $owner->id,
            'name' => 'Fall Fest',
            'event_type_id' => 970102,
            'price' => 3000,
            'status' => 'pending',
            'contract_option' => 'none',
            'notes' => null,
        ]);
        Events::factory()->create([
            'id' => 970303,
            'eventable_type' => Bookings::class,
            'eventable_id' => $festival->id,
            'event_type_id' => 970102,
            'title' => 'Fall Fest Night 2',
            'date' => '2026-12-06',
            'start_time' => '20:00',
            'end_time' => '23:30',
            'venue_name' => 'Parc',
            'venue_address' => null,
            'notes' => null,
            'additional_data' => ['public' => false],
        ]);
        Events::factory()->create([
            'id' => 970302,
            'eventable_type' => Bookings::class,
            'eventable_id' => $festival->id,
            'event_type_id' => 970102,
            'title' => 'Fall Fest Night 1',
            'date' => '2026-12-05',
            'start_time' => '19:00',
            'end_time' => '22:00',
            'venue_name' => 'Parc',
            'venue_address' => null,
            'notes' => null,
            'additional_data' => [],
        ]);

        // Legacy band event (polymorphic) — appears on /events only.
        $legacy = BandEvents::factory()->create([
            'id' => 970401,
            'band_id' => self::BAND_ID,
            'event_type_id' => 970102,
        ]);
        Events::factory()->create([
            'id' => 970304,
            'eventable_type' => BandEvents::class,
            'eventable_id' => $legacy->id,
            'event_type_id' => 970102,
            'title' => 'Legacy Gig',
            'date' => '2026-11-20',
            'start_time' => null,
            'end_time' => null,
            'venue_name' => null,
            'venue_address' => null,
            'notes' => 'Old style',
            'additional_data' => ['public' => '1'],
        ]);

        // Another band's booking must never leak.
        $other = Bookings::factory()->create([
            'id' => 970299,
            'band_id' => self::OTHER_BAND_ID,
            'name' => 'Not Ours',
            'event_type_id' => 970101,
            'price' => 999,
        ]);
        Events::factory()->create([
            'id' => 970399,
            'eventable_type' => Bookings::class,
            'eventable_id' => $other->id,
            'event_type_id' => 970101,
            'date' => '2026-11-14',
        ]);
    }

    private function api(): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->plainToken]);
    }

    private function assertMatchesSnapshot(string $name, string $content): void
    {
        $path = base_path("tests/Fixtures/developer-api/{$name}.json");

        if (! file_exists($path)) {
            if (! env('RECORD_SNAPSHOTS')) {
                $this->fail("Missing snapshot {$name}.json — run with RECORD_SNAPSHOTS=1 to record it.");
            }

            @mkdir(dirname($path), 0755, true);
            file_put_contents($path, $content);
        }

        $this->assertSame(file_get_contents($path), $content, "Response body for {$name} changed.");
    }

    public function test_booked_dates_unfiltered(): void
    {
        $response = $this->api()->getJson('/api/booked-dates')->assertOk();
        $this->assertMatchesSnapshot('booked-dates', $response->getContent());
    }

    public function test_booked_dates_filtered(): void
    {
        $response = $this->api()->getJson('/api/booked-dates?from=2026-11-01&to=2026-11-30&after=2026-11-01')->assertOk();
        $this->assertMatchesSnapshot('booked-dates-filtered', $response->getContent());

        $response = $this->api()->getJson('/api/booked-dates?date=2026-12-06&before=2027-01-01')->assertOk();
        $this->assertMatchesSnapshot('booked-dates-date', $response->getContent());
    }

    public function test_booked_dates_validation_error(): void
    {
        $response = $this->api()->getJson('/api/booked-dates?from=11/01/2026')->assertStatus(422);
        $this->assertMatchesSnapshot('booked-dates-422', $response->getContent());
    }

    public function test_events_unfiltered(): void
    {
        $response = $this->api()->getJson('/api/events')->assertOk();
        $this->assertMatchesSnapshot('events', $response->getContent());
    }

    public function test_events_filtered(): void
    {
        $response = $this->api()->getJson('/api/events?from=2026-11-15&before=2026-12-06')->assertOk();
        $this->assertMatchesSnapshot('events-filtered', $response->getContent());
    }

    public function test_bookings_index_and_show(): void
    {
        $response = $this->api()->getJson('/api/bookings')->assertOk();
        $this->assertMatchesSnapshot('bookings-index', $response->getContent());

        $response = $this->api()->getJson('/api/bookings/970202')->assertOk();
        $this->assertMatchesSnapshot('bookings-show', $response->getContent());

        $response = $this->api()->getJson('/api/bookings/970299')->assertNotFound();
        $this->assertMatchesSnapshot('bookings-show-404', $response->getContent());
    }

    public function test_bookings_store(): void
    {
        $response = $this->api()->postJson('/api/bookings', [
            'name' => 'New Gig',
            'event_type_id' => 970102,
            'date' => '2027-01-09',
            'start_time' => '20:00',
            'duration' => 3,
            'price' => 1250.5,
            'venue_name' => 'Blue Moon',
            'venue_address' => '215 E Convent St',
            'contract_option' => 'external',
            'notes' => 'Via API',
        ])->assertCreated();

        $booking = Bookings::where('name', 'New Gig')->firstOrFail();
        $event = $booking->events()->firstOrFail();
        $content = str_replace(
            ['"id":' . $booking->id . ',', '"id":' . $event->id . ','],
            ['"id":"__BOOKING_ID__",', '"id":"__EVENT_ID__",'],
            $response->getContent()
        );

        $this->assertMatchesSnapshot('bookings-store', $content);

        $response = $this->api()->postJson('/api/bookings', ['name' => ''])->assertStatus(422);
        $this->assertMatchesSnapshot('bookings-store-422', $response->getContent());
    }

    public function test_bookings_add_contact(): void
    {
        $response = $this->api()->postJson('/api/bookings/970201/contacts', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '337-555-0100',
            'role' => 'Bride',
        ])->assertCreated();

        $contact = Contacts::where('email', 'jane@example.com')->sole();
        $pivotId = Bookings::findOrFail(970201)->contacts()->sole()->pivot->id;
        $content = str_replace(
            ['"id":' . $contact->id . ',', '"contact_id":' . $contact->id . ',', '"id":' . $pivotId . ','],
            ['"id":"__ID__",', '"contact_id":"__ID__",', '"id":"__ID__",'],
            $response->getContent()
        );
        $this->assertMatchesSnapshot('bookings-add-contact', $content);

        $response = $this->api()->postJson('/api/bookings/970299/contacts', ['name' => 'X', 'email' => 'x@example.com'])->assertNotFound();
        $this->assertMatchesSnapshot('bookings-add-contact-404', $response->getContent());

        $response = $this->api()->postJson('/api/bookings/970201/contacts', ['name' => 'X'])->assertStatus(422);
        $this->assertMatchesSnapshot('bookings-add-contact-422', $response->getContent());
    }

    public function test_bookings_update_and_destroy(): void
    {
        $response = $this->api()->patchJson('/api/bookings/970202', [
            'name' => 'Fall Fest 2026',
            'price' => 3200,
            'date' => '2026-12-04',
            'venue_address' => '100 Park Ave',
            'status' => 'confirmed',
        ])->assertOk();
        $this->assertMatchesSnapshot('bookings-update', $response->getContent());

        $response = $this->api()->putJson('/api/bookings/970202', ['status' => 'bogus'])->assertStatus(422);
        $this->assertMatchesSnapshot('bookings-update-422', $response->getContent());

        $response = $this->api()->deleteJson('/api/bookings/970299')->assertNotFound();
        $this->assertMatchesSnapshot('bookings-destroy-404', $response->getContent());

        $response = $this->api()->deleteJson('/api/bookings/970201')->assertOk();
        $this->assertMatchesSnapshot('bookings-destroy', $response->getContent());
        $this->assertNull(Bookings::find(970201));
    }
}
