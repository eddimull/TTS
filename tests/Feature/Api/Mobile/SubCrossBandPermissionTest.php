<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\BandSubs;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the cross-band privilege-escalation bug:
 *
 * Mobile token abilities are built band-agnostically (a flat set of
 * "read:bookings"/"write:events" strings across ALL the user's bands). The
 * EnsureUserInBand middleware then gates a band-scoped route on
 * `allBands()->contains($band)` (which includes bands the user only SUBS for)
 * plus a band-agnostic `tokenCan('read:bookings')`. So a user who owns one band
 * (and therefore holds a global read:bookings ability) could read a DIFFERENT
 * band's bookings where they are only a sub.
 *
 * These tests mint the token via the real TokenService::buildAbilities() flow
 * (NOT the default ['*'] token) so the middleware's ability check is actually
 * exercised the way it is in production.
 */
class SubCrossBandPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $ownedBand;
    private Bands $subBand;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user      = User::factory()->create();
        $this->ownedBand = Bands::factory()->create();
        $this->subBand   = Bands::factory()->create();

        // The user OWNS ownedBand → buildAbilities grants a global read:bookings.
        BandOwners::create([
            'user_id' => $this->user->id,
            'band_id' => $this->ownedBand->id,
        ]);

        // The user is only a SUB for subBand.
        BandSubs::create([
            'user_id' => $this->user->id,
            'band_id' => $this->subBand->id,
        ]);

        // Mint the token with REAL abilities, exactly as the login flow does.
        $abilities   = (new TokenService())->buildAbilities($this->user);
        $this->token = $this->user->createToken('test-device', $abilities)->plainTextToken;
    }

    public function test_owner_token_carries_global_read_bookings_ability(): void
    {
        // Sanity check on the precondition: owning one band leaks a bare,
        // band-agnostic read:bookings ability into the token.
        $abilities = (new TokenService())->buildAbilities($this->user);
        $this->assertContains('read:bookings', $abilities);
    }

    public function test_sub_cannot_read_bookings_of_band_they_only_sub_for(): void
    {
        Bookings::factory()->create(['band_id' => $this->subBand->id]);

        $this->withToken($this->token)
            ->withHeaders(['X-Band-ID' => $this->subBand->id])
            ->getJson("/api/mobile/bands/{$this->subBand->id}/bookings")
            ->assertStatus(403);
    }

    public function test_sub_cannot_read_finances_of_band_they_only_sub_for(): void
    {
        $this->withToken($this->token)
            ->withHeaders(['X-Band-ID' => $this->subBand->id])
            ->getJson("/api/mobile/bands/{$this->subBand->id}/finances")
            ->assertStatus(403);
    }

    public function test_sub_sees_only_assigned_gig_charts_not_whole_library(): void
    {
        // Subs may reach the per-band charts route (like events), but must only
        // see charts for gigs they're assigned to — NOT the band's whole library.
        $assignedChart   = \App\Models\Charts::create(['band_id' => $this->subBand->id, 'title' => 'Assigned Chart']);
        $unassignedChart = \App\Models\Charts::create(['band_id' => $this->subBand->id, 'title' => 'Secret Chart']);

        // A booking + event in the sub band, referencing $assignedChart, that the
        // user is assigned to via event_members (sub slot: roster_member_id NULL).
        $booking = Bookings::factory()->create(['band_id' => $this->subBand->id]);
        $event   = \App\Models\Events::factory()->create([
            'eventable_id'    => $booking->id,
            'eventable_type'  => \App\Models\Bookings::class,
            'date'            => now()->addDays(5)->format('Y-m-d'),
            'additional_data' => ['performance' => ['charts' => [['id' => $assignedChart->id, 'title' => 'Assigned Chart']]]],
        ]);
        \App\Models\EventMember::create([
            'event_id'         => $event->id,
            'band_id'          => $this->subBand->id,
            'user_id'          => $this->user->id,
            'roster_member_id' => null,
            'name'             => $this->user->name,
        ]);

        $titles = collect(
            $this->withToken($this->token)
                ->withHeaders(['X-Band-ID' => $this->subBand->id])
                ->getJson("/api/mobile/bands/{$this->subBand->id}/charts")
                ->assertOk()
                ->json('charts')
        )->pluck('title');

        $this->assertContains('Assigned Chart', $titles);
        $this->assertNotContains('Secret Chart', $titles);
    }

    public function test_owner_can_still_read_own_bands_bookings(): void
    {
        // The fix must NOT break the legitimate case: reading bookings of a band
        // you actually own.
        Bookings::factory()->create(['band_id' => $this->ownedBand->id]);

        $this->withToken($this->token)
            ->withHeaders(['X-Band-ID' => $this->ownedBand->id])
            ->getJson("/api/mobile/bands/{$this->ownedBand->id}/bookings")
            ->assertOk();
    }

    public function test_sub_can_still_read_events_of_sub_band(): void
    {
        // Events are intentionally readable by subs — the fix must preserve this.
        $this->withToken($this->token)
            ->withHeaders(['X-Band-ID' => $this->subBand->id])
            ->getJson("/api/mobile/bands/{$this->subBand->id}/events")
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Tier 0: endpoints that previously had NO band authorization at all
    // (any authenticated user could reach any band's data by object id).
    // -------------------------------------------------------------------------

    public function test_stranger_cannot_read_rehearsal_by_key(): void
    {
        $schedule = \App\Models\RehearsalSchedule::factory()->create([
            'band_id' => $this->subBand->id,
        ]);

        // A user with no relationship to subBand at all.
        $stranger      = User::factory()->create();
        $strangerToken = $stranger->createToken('test-device')->plainTextToken;

        $key = "virtual-rehearsal-{$schedule->id}-" . now()->addDays(3)->format('Y-m-d');

        $this->withToken($strangerToken)
            ->withHeaders(['X-Band-ID' => $this->subBand->id])
            ->getJson('/api/mobile/rehearsals/by-key/' . $key)
            ->assertStatus(403);
    }

    public function test_stranger_cannot_update_rehearsal_notes(): void
    {
        $schedule = \App\Models\RehearsalSchedule::factory()->create([
            'band_id' => $this->subBand->id,
        ]);
        $rehearsal = \App\Models\Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'notes'                 => 'original notes',
        ]);

        $stranger      = User::factory()->create();
        $strangerToken = $stranger->createToken('test-device')->plainTextToken;

        $this->withToken($strangerToken)
            ->withHeaders(['X-Band-ID' => $this->subBand->id])
            ->patchJson("/api/mobile/rehearsals/{$rehearsal->id}/notes", ['notes' => 'pwned'])
            ->assertStatus(403);

        // The blocked write must not have mutated the notes.
        $this->assertSame('original notes', $rehearsal->fresh()->notes);
    }

    public function test_stranger_cannot_view_live_setlist_session(): void
    {
        $booking = Bookings::factory()->create(['band_id' => $this->subBand->id]);
        $event   = \App\Models\Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => \App\Models\Bookings::class,
            'date'           => now()->addDays(2)->format('Y-m-d'),
        ]);

        $stranger      = User::factory()->create();
        $strangerToken = $stranger->createToken('test-device')->plainTextToken;

        $this->withToken($strangerToken)
            ->withHeaders(['X-Band-ID' => $this->subBand->id])
            ->getJson("/api/mobile/setlist/events/{$event->key}/session")
            ->assertStatus(403);
    }

    public function test_sub_cannot_start_live_setlist_session(): void
    {
        // A sub holds read:events (and via the leaked token, write:events from
        // their owned band) but must not be able to START a session on a band
        // they only sub for — start requires canWrite('events', band).
        $booking = Bookings::factory()->create(['band_id' => $this->subBand->id]);
        $event   = \App\Models\Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => \App\Models\Bookings::class,
            'date'           => now()->addDays(2)->format('Y-m-d'),
        ]);

        $this->withToken($this->token)
            ->withHeaders(['X-Band-ID' => $this->subBand->id])
            ->postJson("/api/mobile/setlist/events/{$event->key}/session")
            ->assertStatus(403);
    }
}
