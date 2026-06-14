<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandSubs;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\EventSubs;
use App\Models\EventTypes;
use App\Models\User;
use App\Services\Mobile\TokenService;
use App\Services\UserEventsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression test for: a user who is ONLY a sub of a band sees their events on
 * the WEB app (UserEventsService) but NOT on mobile.
 *
 * Reproduces production user 40 (band 1):
 *  - role `sub` WITHOUT the Spatie `read:events` permission (matches prod, where
 *    the sub role only carries view-* permissions)
 *  - in band_subs for the band (no band_owners / band_members)
 *  - assigned to some events via event_members (roster_member_id NULL) / event_subs
 *
 * The mobile events index must return EXACTLY the set UserEventsService returns
 * for the same user, using a REAL token built by TokenService (not a wildcard
 * test token, which masked the ability gate).
 */
class MobileSubEventsParityTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();

        // Sub role is global; assignments live at team_id=0.
        setPermissionsTeamId(0);

        // Create the `sub` role WITHOUT read:events — matching production, where
        // the sub role carries only view-* permissions and relies on the
        // isSubOfBand() exception in User::canRead() for event access.
        Role::firstOrCreate(['name' => 'sub', 'guard_name' => 'web']);

        $this->band = Bands::factory()->create();
    }

    /**
     * Build a sub-only user: in band_subs for the band, role `sub`, assigned to
     * the given events. Returns the user.
     */
    private function makeSubAssignedTo(array $events): User
    {
        $sub = User::factory()->create();
        $sub->assignRole('sub');

        BandSubs::firstOrCreate(['user_id' => $sub->id, 'band_id' => $this->band->id]);

        foreach ($events as $event) {
            EventMember::create([
                'event_id'         => $event->id,
                'band_id'          => $this->band->id,
                'user_id'          => $sub->id,
                'roster_member_id' => null,
                'name'             => $sub->name,
            ]);
        }

        return $sub;
    }

    private function makeBookingEvent(string $date): Events
    {
        $eventType = EventTypes::factory()->create();
        $booking   = Bookings::factory()->create(['band_id' => $this->band->id]);

        return Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => $date,
        ]);
    }

    private function realTokenFor(User $user): string
    {
        $abilities = app(TokenService::class)->buildAbilities($user);

        return $user->createToken('test-device', $abilities)->plainTextToken;
    }

    public function test_sub_can_reach_events_index_with_real_token(): void
    {
        $assigned = $this->makeBookingEvent(now()->addDays(7)->format('Y-m-d'));
        $sub      = $this->makeSubAssignedTo([$assigned]);

        $this->withToken($this->realTokenFor($sub))
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson("/api/mobile/bands/{$this->band->id}/events")
            ->assertOk();
    }

    public function test_mobile_returns_exactly_the_web_event_set_for_a_sub(): void
    {
        // Two assigned (future) events and one unassigned band event.
        $assigned1  = $this->makeBookingEvent(now()->addDays(7)->format('Y-m-d'));
        $assigned2  = $this->makeBookingEvent(now()->addDays(14)->format('Y-m-d'));
        $unassigned = $this->makeBookingEvent(now()->addDays(21)->format('Y-m-d'));

        $sub = $this->makeSubAssignedTo([$assigned1, $assigned2]);

        // What web returns for this user (the source of truth).
        Auth::login($sub);
        $webIds = (new UserEventsService())->getEvents()->pluck('id')->sort()->values()->all();
        Auth::logout();

        $response = $this->withToken($this->realTokenFor($sub))
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson("/api/mobile/bands/{$this->band->id}/events")
            ->assertOk();

        $mobileIds = collect($response->json('events'))->pluck('id')->sort()->values()->all();

        // Mobile must equal web exactly.
        $this->assertSame($webIds, $mobileIds, 'Mobile event set must match web UserEventsService set.');

        // And concretely: the two assigned events are present, the unassigned is not.
        $this->assertContains($assigned1->id, $mobileIds);
        $this->assertContains($assigned2->id, $mobileIds);
        $this->assertNotContains($unassigned->id, $mobileIds);
    }

    public function test_mobile_excludes_past_events_outside_default_window_like_web(): void
    {
        // Assigned but well in the past (outside web's now-72h default window).
        $pastAssigned   = $this->makeBookingEvent(now()->subDays(30)->format('Y-m-d'));
        $futureAssigned = $this->makeBookingEvent(now()->addDays(10)->format('Y-m-d'));

        $sub = $this->makeSubAssignedTo([$pastAssigned, $futureAssigned]);

        Auth::login($sub);
        $webIds = (new UserEventsService())->getEvents()->pluck('id')->sort()->values()->all();
        Auth::logout();

        $response = $this->withToken($this->realTokenFor($sub))
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson("/api/mobile/bands/{$this->band->id}/events")
            ->assertOk();

        $mobileIds = collect($response->json('events'))->pluck('id')->sort()->values()->all();

        $this->assertSame($webIds, $mobileIds);
        $this->assertNotContains($pastAssigned->id, $mobileIds, 'Past event outside 72h window must be hidden on mobile, as on web.');
        $this->assertContains($futureAssigned->id, $mobileIds);
    }
}
