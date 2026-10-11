<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\BandmateServer;
use App\Mcp\Support\McpScopes;
use App\Mcp\Tools\CheckAvailability;
use App\Mcp\Tools\CreateBooking;
use App\Mcp\Tools\DeleteBooking;
use App\Mcp\Tools\EstimatePrice;
use App\Mcp\Tools\GetBooking;
use App\Mcp\Tools\ListBands;
use App\Mcp\Tools\ListBookings;
use App\Mcp\Tools\ListEvents;
use App\Mcp\Tools\ListEventTypes;
use App\Mcp\Tools\UpdateBooking;
use App\Models\BandApiToken;
use App\Models\Bands;
use App\Models\BandSubs;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\Concerns\UsesPassportKeys;
use Tests\TestCase;

class BandmateToolsTest extends TestCase
{
    use RefreshDatabase;
    use UsesPassportKeys;

    private const ALL_SCOPES = [
        McpScopes::BOOKINGS_READ,
        McpScopes::BOOKINGS_WRITE,
        McpScopes::EVENTS_READ,
        McpScopes::PRICING_READ,
    ];

    private User $owner;
    private Bands $band;
    private Bands $otherBand;
    private EventTypes $wedding;
    private Bookings $booking;
    private Bookings $otherBooking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassportKeys();

        Bus::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00'));

        $this->owner = User::factory()->create(['name' => 'Olive Owner']);
        $this->band = Bands::factory()->create(['name' => 'Alpha Band']);
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        $this->otherBand = Bands::factory()->create(['name' => 'Zeta Band']);
        $this->otherBand->owners()->create(['user_id' => User::factory()->create()->id]);

        $this->wedding = EventTypes::factory()->create(['name' => 'Wedding']);

        $this->booking = $this->bookingOn($this->band, 'Smith Wedding', '2026-11-14', 4500);
        $this->otherBooking = $this->bookingOn($this->otherBand, 'Secret Gig', '2026-11-14', 9999);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function bookingOn(Bands $band, string $name, string $date, int $price, string $status = 'confirmed'): Bookings
    {
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'name' => $name,
            'event_type_id' => $this->wedding->id,
            'price' => $price,
            'status' => $status,
            'contract_option' => 'none',
        ]);
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'event_type_id' => $this->wedding->id,
            'title' => $name,
            'date' => $date,
            'start_time' => '18:00',
            'end_time' => '22:00',
        ]);

        return $booking;
    }

    private function memberWith(array $permissions, ?Bands $band = null): User
    {
        $band ??= $this->band;
        $member = User::factory()->create();
        $band->members()->create(['user_id' => $member->id]);

        if ($permissions) {
            setPermissionsTeamId($band->id);
            $member->givePermissionTo($permissions);
            setPermissionsTeamId(0);
        }

        return $member;
    }

    private function asMcp(User $user, array $scopes = self::ALL_SCOPES): static
    {
        Passport::actingAs($user, $scopes, 'mcp');

        return $this;
    }

    /**
     * The JSON a tool returned in its text content.
     */
    private function toolJson(TestResponse $response): array
    {
        $response->assertHasNoErrors();

        return json_decode((fn () => $this->content()[0])->call($response), true, flags: JSON_THROW_ON_ERROR);
    }

    // ── list_bands / list_event_types ───────────────────────────────────

    public function test_list_bands_lists_only_owned_and_member_bands_with_role_and_tools(): void
    {
        $memberBand = Bands::factory()->create(['name' => 'Beta Band']);
        $memberBand->members()->create(['user_id' => $this->owner->id]);
        setPermissionsTeamId($memberBand->id);
        $this->owner->givePermissionTo(['read:bookings']);
        setPermissionsTeamId(0);

        $subBand = Bands::factory()->create(['name' => 'Sub Band']);
        BandSubs::create(['user_id' => $this->owner->id, 'band_id' => $subBand->id]);

        $this->asMcp($this->owner);
        $data = $this->toolJson(BandmateServer::tool(ListBands::class));

        $this->assertSame(['Alpha Band', 'Beta Band'], array_column($data['bands'], 'name'));
        $this->assertSame(2, $data['total']);
        $this->assertSame(self::ALL_SCOPES, $data['granted_scopes']);

        [$alpha, $beta] = $data['bands'];
        $this->assertSame('owner', $alpha['role']);
        $this->assertSame([
            'check_availability', 'list_bookings', 'get_booking', 'create_booking',
            'update_booking', 'delete_booking', 'list_events', 'estimate_price',
        ], $alpha['tools']);

        $this->assertSame('member', $beta['role']);
        $this->assertSame(['check_availability', 'list_bookings', 'get_booking'], $beta['tools']);
    }

    public function test_list_bands_tools_reflect_granted_scopes(): void
    {
        $this->asMcp($this->owner, [McpScopes::EVENTS_READ]);
        $data = $this->toolJson(BandmateServer::tool(ListBands::class));

        $this->assertSame([McpScopes::EVENTS_READ], $data['granted_scopes']);
        $this->assertSame(['list_events'], $data['bands'][0]['tools']);
    }

    public function test_umbrella_mcp_use_scope_counts_as_every_scope(): void
    {
        $this->asMcp($this->owner, [McpScopes::UMBRELLA]);
        $data = $this->toolJson(BandmateServer::tool(ListBands::class));

        $this->assertSame(self::ALL_SCOPES, $data['granted_scopes']);
    }

    public function test_list_event_types(): void
    {
        $this->asMcp($this->owner, []);
        $data = $this->toolJson(BandmateServer::tool(ListEventTypes::class));

        $this->assertContains(['id' => $this->wedding->id, 'name' => 'Wedding'], $data['event_types']);
    }

    // ── check_availability ──────────────────────────────────────────────

    public function test_check_availability_for_a_date(): void
    {
        $this->bookingOn($this->band, 'Cancelled Gig', '2026-11-21', 100, 'cancelled');
        $this->asMcp($this->owner, [McpScopes::BOOKINGS_READ]);

        $busy = $this->toolJson(BandmateServer::tool(CheckAvailability::class, ['band_id' => $this->band->id, 'date' => '2026-11-14']));
        $this->assertFalse($busy['available']);
        $this->assertSame(['Smith Wedding'], array_column($busy['bookings'], 'name'));
        $this->assertSame(['date' => '2026-11-14'], $busy['filters']);
        $this->assertSame('4500.00', $busy['bookings'][0]['price']);

        $cancelledOnly = $this->toolJson(BandmateServer::tool(CheckAvailability::class, ['band_id' => $this->band->id, 'date' => '2026-11-21']));
        $this->assertTrue($cancelledOnly['available']);

        $free = $this->toolJson(BandmateServer::tool(CheckAvailability::class, ['band_id' => $this->band->id, 'date' => '2026-11-15']));
        $this->assertTrue($free['available']);
        $this->assertSame(0, $free['total']);
    }

    public function test_check_availability_range_ignores_null_filters_and_matches_rest_rows(): void
    {
        $this->bookingOn($this->band, 'December Gig', '2026-12-05', 2000);
        $this->asMcp($this->owner, [McpScopes::BOOKINGS_READ]);

        $data = $this->toolJson(BandmateServer::tool(CheckAvailability::class, [
            'band_id' => $this->band->id, 'from' => '2026-12-01', 'to' => '2026-12-31', 'date' => null,
        ]));

        $this->assertSame(['December Gig'], array_column($data['bookings'], 'name'));
        $this->assertSame(['from' => '2026-12-01', 'to' => '2026-12-31'], $data['filters']);
        $this->assertArrayNotHasKey('available', $data);

        // Same rows as the developer API for the same band and filters.
        $this->artisan('db:seed', ['--class' => 'ApiPermissionsSeeder']);
        $plain = Str::random(60);
        BandApiToken::create(['band_id' => $this->band->id, 'token' => hash('sha256', $plain), 'name' => 't', 'is_active' => true])
            ->givePermissionTo('api:read-bookings');
        $rest = $this->withToken($plain)->getJson('/api/booked-dates?from=2026-12-01&to=2026-12-31')->json();
        $this->assertSame($rest['bookings'], $data['bookings']);
    }

    public function test_check_availability_validates_dates(): void
    {
        $this->asMcp($this->owner, [McpScopes::BOOKINGS_READ]);

        BandmateServer::tool(CheckAvailability::class, ['band_id' => $this->band->id, 'date' => '11/14/2026'])
            ->assertHasErrors(['date']);
    }

    public function test_check_availability_requires_bookings_read_scope(): void
    {
        $this->asMcp($this->owner, [McpScopes::EVENTS_READ]);

        BandmateServer::tool(CheckAvailability::class, ['band_id' => $this->band->id])
            ->assertHasErrors(["not granted the 'bookings:read' scope"]);
    }

    public function test_non_member_band_is_rejected_without_leaking_data(): void
    {
        $this->asMcp($this->owner);

        foreach ([
            [CheckAvailability::class, []],
            [ListEvents::class, []],
            [ListBookings::class, []],
            [GetBooking::class, ['booking_id' => $this->otherBooking->id]],
            [DeleteBooking::class, ['booking_id' => $this->otherBooking->id]],
            [EstimatePrice::class, ['event_type_id' => $this->wedding->id, 'date' => '2027-05-15']],
        ] as [$tool, $args]) {
            BandmateServer::tool($tool, ['band_id' => $this->otherBand->id, ...$args])
                ->assertHasErrors(["Band {$this->otherBand->id} is not one of your bands"])
                ->assertDontSee(['Secret Gig', 'Zeta Band']);
        }

        $this->assertNotNull(Bookings::find($this->otherBooking->id));
    }

    public function test_a_sub_cannot_use_band_tools(): void
    {
        $sub = User::factory()->create();
        BandSubs::create(['user_id' => $sub->id, 'band_id' => $this->band->id]);
        $this->asMcp($sub);

        BandmateServer::tool(ListEvents::class, ['band_id' => $this->band->id])
            ->assertHasErrors(['is not one of your bands']);
    }

    public function test_band_id_is_required(): void
    {
        $this->asMcp($this->owner);

        BandmateServer::tool(ListBookings::class, [])->assertHasErrors(['band id']);
    }

    // ── list_events ─────────────────────────────────────────────────────

    public function test_list_events_for_member_with_read_events(): void
    {
        $member = $this->memberWith(['read:events']);
        $this->asMcp($member, [McpScopes::EVENTS_READ]);

        $data = $this->toolJson(BandmateServer::tool(ListEvents::class, ['band_id' => $this->band->id, 'from' => '2026-11-01']));

        $this->assertSame(['Smith Wedding'], array_column($data['events'], 'title'));
        $this->assertSame('Bookings', $data['events'][0]['eventable_type']);
        $this->assertSame(['from' => '2026-11-01'], $data['filters']);
    }

    public function test_list_events_member_without_permission_is_rejected(): void
    {
        $member = $this->memberWith(['read:bookings']);
        $this->asMcp($member);

        BandmateServer::tool(ListEvents::class, ['band_id' => $this->band->id])
            ->assertHasErrors(["You don't have permission to read events in Alpha Band"]);
    }

    public function test_list_events_requires_events_read_scope(): void
    {
        $this->asMcp($this->owner, [McpScopes::BOOKINGS_READ]);

        BandmateServer::tool(ListEvents::class, ['band_id' => $this->band->id])
            ->assertHasErrors(["'events:read' scope"]);
    }

    // ── list_bookings / get_booking ─────────────────────────────────────

    public function test_list_bookings(): void
    {
        $member = $this->memberWith(['read:bookings']);
        $this->asMcp($member, [McpScopes::BOOKINGS_READ]);

        $data = $this->toolJson(BandmateServer::tool(ListBookings::class, ['band_id' => $this->band->id]));

        $this->assertSame(1, $data['total']);
        $this->assertSame('Smith Wedding', $data['bookings'][0]['name']);
        $this->assertSame(['id' => $this->band->id, 'name' => 'Alpha Band'], $data['band']);
    }

    public function test_get_booking_and_other_bands_booking_is_not_found(): void
    {
        $this->asMcp($this->owner, [McpScopes::BOOKINGS_READ]);

        $data = $this->toolJson(BandmateServer::tool(GetBooking::class, ['band_id' => $this->band->id, 'booking_id' => $this->booking->id]));
        $this->assertSame($this->booking->id, $data['booking']['id']);
        $this->assertSame('2026-11-14', $data['booking']['start_date']);

        BandmateServer::tool(GetBooking::class, ['band_id' => $this->band->id, 'booking_id' => $this->otherBooking->id])
            ->assertHasErrors(['not found in Alpha Band'])
            ->assertDontSee('Secret Gig');
    }

    public function test_member_without_read_bookings_cannot_list_bookings(): void
    {
        $member = $this->memberWith([]);
        $this->asMcp($member);

        BandmateServer::tool(ListBookings::class, ['band_id' => $this->band->id])
            ->assertHasErrors(["You don't have permission to read bookings"]);
    }

    // ── create / update / delete ────────────────────────────────────────

    public function test_create_booking_as_member_with_write_permission(): void
    {
        $member = $this->memberWith(['read:bookings', 'write:bookings']);
        $this->asMcp($member, [McpScopes::BOOKINGS_WRITE]);

        $data = $this->toolJson(BandmateServer::tool(CreateBooking::class, [
            'band_id' => $this->band->id,
            'name' => 'Jones Party',
            'event_type_id' => $this->wedding->id,
            'date' => '2027-02-13',
            'start_time' => '19:30',
            'duration' => 4,
            'price' => 1250.5,
            'contract_option' => 'none',
            'venue_name' => 'The Barn',
        ]));

        $this->assertSame('Booking created successfully', $data['message']);
        $this->assertSame('1250.50', $data['booking']['price']);
        $this->assertSame('draft', $data['booking']['status']);
        $this->assertSame('23:30', $data['booking']['events'][0]['end_time']);

        $booking = Bookings::findOrFail($data['booking']['id']);
        $this->assertSame($this->band->id, $booking->band_id);
        $this->assertSame($member->id, $booking->author_id);
    }

    public function test_create_booking_validates_like_the_api(): void
    {
        $this->asMcp($this->owner);

        BandmateServer::tool(CreateBooking::class, [
            'band_id' => $this->band->id,
            'name' => 'Past Gig',
            'event_type_id' => $this->wedding->id,
            'date' => '2020-01-01',
            'start_time' => '19:30',
            'duration' => 4,
            'price' => 100,
            'contract_option' => 'none',
        ])->assertHasErrors(['date']);

        $this->assertFalse(Bookings::where('name', 'Past Gig')->exists());
    }

    public function test_write_tools_need_write_permission(): void
    {
        $reader = $this->memberWith(['read:bookings']);
        $this->asMcp($reader);

        BandmateServer::tool(CreateBooking::class, ['band_id' => $this->band->id])
            ->assertHasErrors(["You don't have permission to write bookings in Alpha Band"]);
        BandmateServer::tool(UpdateBooking::class, ['band_id' => $this->band->id, 'booking_id' => $this->booking->id, 'name' => 'X'])
            ->assertHasErrors(["permission to write bookings"]);
        BandmateServer::tool(DeleteBooking::class, ['band_id' => $this->band->id, 'booking_id' => $this->booking->id])
            ->assertHasErrors(["permission to write bookings"]);

        $this->assertSame('Smith Wedding', $this->booking->fresh()->name);
    }

    public function test_write_tools_need_write_scope(): void
    {
        $this->asMcp($this->owner, [McpScopes::BOOKINGS_READ]);

        BandmateServer::tool(DeleteBooking::class, ['band_id' => $this->band->id, 'booking_id' => $this->booking->id])
            ->assertHasErrors(["'bookings:write' scope"]);

        $this->assertNotNull($this->booking->fresh());
    }

    public function test_update_booking_changes_only_given_fields(): void
    {
        $this->asMcp($this->owner, [McpScopes::BOOKINGS_WRITE]);

        $data = $this->toolJson(BandmateServer::tool(UpdateBooking::class, [
            'band_id' => $this->band->id,
            'booking_id' => $this->booking->id,
            'price' => 5000,
            'date' => '2026-11-15',
            'status' => null,      // null = unchanged for non-clearable fields
            'notes' => null,       // null clears notes
        ]));

        $this->assertSame('5000.00', $data['booking']['price']);
        $this->assertSame('confirmed', $data['booking']['status']);
        $this->assertSame('2026-11-15', $data['booking']['events'][0]['date']);
        $this->assertNull($this->booking->fresh()->notes);
    }

    public function test_update_booking_needs_a_field(): void
    {
        $this->asMcp($this->owner);

        BandmateServer::tool(UpdateBooking::class, ['band_id' => $this->band->id, 'booking_id' => $this->booking->id])
            ->assertHasErrors(['Nothing to update']);
    }

    public function test_delete_booking(): void
    {
        $this->asMcp($this->owner, [McpScopes::BOOKINGS_WRITE]);

        $data = $this->toolJson(BandmateServer::tool(DeleteBooking::class, ['band_id' => $this->band->id, 'booking_id' => $this->booking->id]));

        $this->assertSame(['id' => $this->booking->id, 'name' => 'Smith Wedding'], $data['booking']);
        $this->assertNull(Bookings::find($this->booking->id));
    }

    public function test_tool_annotations(): void
    {
        $this->assertSame(['destructiveHint' => true], app(DeleteBooking::class)->toArray()['annotations']);
        $this->assertSame(['destructiveHint' => false], app(CreateBooking::class)->toArray()['annotations']);
        $this->assertSame(['readOnlyHint' => true, 'idempotentHint' => true], app(ListBookings::class)->toArray()['annotations']);
        $this->assertSame('delete_booking', app(DeleteBooking::class)->name());
    }

    // ── estimate_price ──────────────────────────────────────────────────

    public function test_estimate_price_for_owner_matches_the_developer_api(): void
    {
        $this->asMcp($this->owner, [McpScopes::PRICING_READ]);
        $input = ['event_type_id' => $this->wedding->id, 'date' => '2027-05-15', 'start_time' => '19:00', 'end_time' => '23:00', 'distance_miles' => 20];

        $response = BandmateServer::tool(EstimatePrice::class, ['band_id' => $this->band->id, ...$input]);
        $response->assertHasNoErrors();

        $plain = Str::random(60);
        BandApiToken::create(['band_id' => $this->band->id, 'token' => hash('sha256', $plain), 'name' => 't', 'is_active' => true])
            ->givePermissionTo('api:read-pricing');
        $rest = $this->withToken($plain)->postJson('/api/price-estimate', $input)->assertOk();

        // Same body as the REST endpoint (which escapes slashes/unicode; the tool doesn't).
        $toolText = (fn () => $this->content()[0])->call($response);
        $this->assertSame(
            json_encode($rest->json(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
            $toolText
        );
        $this->assertSame(json_decode($rest->getContent(), true), $this->toolJson($response));
    }

    public function test_estimate_price_rejects_members(): void
    {
        $member = $this->memberWith(['read:bookings', 'write:bookings', 'read:events']);
        $this->asMcp($member);

        BandmateServer::tool(EstimatePrice::class, ['band_id' => $this->band->id, 'event_type_id' => $this->wedding->id, 'date' => '2027-05-15'])
            ->assertHasErrors(['Only band owners can use estimate_price']);
    }

    public function test_estimate_price_requires_pricing_scope_and_validates(): void
    {
        $this->asMcp($this->owner, [McpScopes::BOOKINGS_READ]);
        BandmateServer::tool(EstimatePrice::class, ['band_id' => $this->band->id, 'event_type_id' => $this->wedding->id, 'date' => '2027-05-15'])
            ->assertHasErrors(["'pricing:read' scope"]);

        $this->asMcp($this->owner, [McpScopes::PRICING_READ]);
        BandmateServer::tool(EstimatePrice::class, ['band_id' => $this->band->id, 'date' => '2027-05-15'])
            ->assertHasErrors(['event type id']);
    }

    public function test_permissions_are_checked_per_band_for_the_same_user(): void
    {
        // Member of two bands with different grants: no cross-band bleed.
        $member = $this->memberWith(['read:events']);
        $second = Bands::factory()->create(['name' => 'Beta Band']);
        $second->members()->create(['user_id' => $member->id]);
        setPermissionsTeamId($second->id);
        $member->givePermissionTo('read:bookings');
        setPermissionsTeamId(0);

        $this->asMcp($member);
        $data = $this->toolJson(BandmateServer::tool(ListBands::class));
        $tools = array_column($data['bands'], 'tools', 'name');

        $this->assertSame(['list_events'], $tools['Alpha Band']);
        $this->assertSame(['check_availability', 'list_bookings', 'get_booking'], $tools['Beta Band']);
    }
}
