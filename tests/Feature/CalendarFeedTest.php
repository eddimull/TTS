<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendarFeedTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ApiPermissionsSeeder']);
        // Provides the global "sub" role used by UserEventsService entitlement.
        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);
        // The sub role is global (team_id 0), matching UserEventsService.
        setPermissionsTeamId(0);

        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);
    }

    public function test_feed_resolves_user_by_token_and_returns_ics(): void
    {
        Events::factory()->forBand($this->band)->create([
            'title' => 'Saturday Night Gig',
            'date'  => now()->addDays(7)->toDateString(),
        ]);

        $token = $this->owner->getCalendarToken();

        $response = $this->get('/calendar/' . $token . '.ics');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $body = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
        $this->assertStringContainsString('Saturday Night Gig', $body);
    }

    public function test_feed_uses_event_venue_timezone_when_set(): void
    {
        Events::factory()->forBand($this->band)->create([
            'title'          => 'Out Of Town Gig',
            'date'           => now()->addDays(10)->toDateString(),
            'start_time'     => '20:00',
            'end_time'       => '23:00',
            'venue_timezone' => 'America/New_York',
        ]);

        $token = $this->owner->getCalendarToken();
        $body  = $this->get('/calendar/' . $token . '.ics')->assertOk()->getContent();

        // The VEVENT start must be expressed in the venue's timezone, not the
        // app default, so out-of-timezone gigs show the correct local time.
        $this->assertStringContainsString('DTSTART;TZID=America/New_York:', $body);
    }

    public function test_feed_returns_404_for_unknown_token(): void
    {
        $this->get('/calendar/this-token-does-not-exist.ics')->assertNotFound();
    }

    public function test_feed_omits_financial_fields(): void
    {
        Events::factory()->forBand($this->band)->create([
            'title' => 'High Roller Wedding',
            'date'  => now()->addDays(3)->toDateString(),
            'price' => '5000.00',
            'notes' => 'Standard prep notes',
        ]);

        $token = $this->owner->getCalendarToken();
        $body  = $this->get('/calendar/' . $token . '.ics')->assertOk()->getContent();

        // The member-facing feed must never leak pricing/financial data.
        $this->assertStringNotContainsStringIgnoringCase('price', $body);
        $this->assertStringNotContainsStringIgnoringCase('deposit', $body);
        $this->assertStringNotContainsString('5000', $body);
        $this->assertStringNotContainsString('$', $body);
    }

    public function test_sub_only_user_sees_only_assigned_events(): void
    {
        // An event for the band the sub is NOT a member of.
        Events::factory()->forBand($this->band)->create([
            'title' => 'Members Only Show',
            'date'  => now()->addDays(5)->toDateString(),
        ]);

        // A user who is only a sub (no ownership/membership) and is not invited
        // to anything should get an empty (but valid) calendar.
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        $token = $sub->getCalendarToken();
        $body  = $this->get('/calendar/' . $token . '.ics')->assertOk()->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringNotContainsString('Members Only Show', $body);
        $this->assertStringNotContainsString('BEGIN:VEVENT', $body);
    }

    public function test_mobile_endpoint_returns_subscription_urls(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/mobile/me/calendar-feed');

        $response->assertOk();
        $response->assertJsonStructure(['url', 'webcal_url', 'google_subscribe_url']);

        $token = $this->owner->fresh()->calendar_token;
        $this->assertNotEmpty($token);

        $response->assertJsonFragment([
            'webcal_url' => preg_replace('#^https?://#', 'webcal://', route('calendar.feed', ['token' => $token . '.ics'])),
        ]);
    }

    public function test_mobile_reset_rotates_token(): void
    {
        $original = $this->owner->getCalendarToken();

        Sanctum::actingAs($this->owner);

        $this->postJson('/api/mobile/me/calendar-feed/reset')->assertOk();

        $this->assertNotEquals($original, $this->owner->fresh()->calendar_token);

        // The old feed URL must stop working once rotated.
        $this->get('/calendar/' . $original . '.ics')->assertNotFound();
    }

    public function test_mobile_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/mobile/me/calendar-feed')->assertUnauthorized();
    }

    public function test_feed_marks_cancelled_rehearsals(): void
    {
        $schedule = RehearsalSchedule::factory()->weekly()->create([
            'band_id' => $this->band->id,
            'name'    => 'Tuesday Practice',
        ]);
        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $this->band->id,
            'is_cancelled'          => true,
        ]);
        Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'title'          => 'Tuesday Practice',
            'date'           => now()->addDays(4)->toDateString(),
            'start_time'     => '19:00',
            'end_time'       => '21:00',
        ]);

        $token = $this->owner->getCalendarToken();
        $body  = $this->get('/calendar/' . $token . '.ics')->assertOk()->getContent();

        $this->assertStringContainsString('STATUS:CANCELLED', $body);
        $this->assertStringContainsString('Cancelled: Tuesday Practice', $body);
    }
}
