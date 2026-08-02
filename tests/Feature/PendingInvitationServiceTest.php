<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\BandSubInvitation;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\Invitations;
use App\Models\Rehearsal;
use App\Models\RehearsalSub;
use App\Models\User;
use App\Services\PendingInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingInvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sub role + permissions required by acceptBandInvitation().
        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);
        \setPermissionsTeamId(0);
    }

    public function test_applies_pending_member_invitation_and_marks_it_consumed(): void
    {
        $band = Bands::factory()->create();
        $invitation = Invitations::create([
            'band_id'        => $band->id,
            'email'          => 'newbie@example.com',
            'invite_type_id' => PendingInvitationService::MEMBER_INVITE_TYPE,
            'pending'        => true,
        ]);
        $user = User::factory()->create(['email' => 'newbie@example.com']);

        app(PendingInvitationService::class)->applyFor($user);

        $this->assertFalse((bool) $invitation->fresh()->pending);
        $this->assertTrue($user->fresh()->bandMember->contains('id', $band->id));
    }

    public function test_applies_pending_band_sub_invitation_and_marks_it_accepted(): void
    {
        $band = Bands::factory()->create();
        $invitation = BandSubInvitation::factory()->create([
            'band_id' => $band->id,
            'email'   => 'sub@example.com',
            'pending' => true,
        ]);
        $user = User::factory()->create(['email' => 'sub@example.com']);

        app(PendingInvitationService::class)->applyFor($user);

        $invitation->refresh();
        $this->assertFalse((bool) $invitation->pending);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertEquals($user->id, $invitation->user_id);
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);
        $this->assertTrue($user->fresh()->hasRole('sub'));
    }

    public function test_ignores_invitations_for_other_emails(): void
    {
        $band = Bands::factory()->create();
        $invitation = Invitations::create([
            'band_id'        => $band->id,
            'email'          => 'someone-else@example.com',
            'invite_type_id' => PendingInvitationService::MEMBER_INVITE_TYPE,
            'pending'        => true,
        ]);
        $user = User::factory()->create(['email' => 'me@example.com']);

        app(PendingInvitationService::class)->applyFor($user);

        $this->assertTrue((bool) $invitation->fresh()->pending);
        $this->assertFalse($user->fresh()->bandMember->contains('id', $band->id));
    }

    // ── Orphaned assignment backfill ─────────────────────────────────────────
    //
    // Slot assignments (event_members) and rehearsal invites (rehearsal_subs)
    // created before the sub had an account carry user_id NULL. Calendar and
    // rehearsal visibility key on user_id, so registration must link them.

    private function makeEventForBand(Bands $band): Events
    {
        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        return Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);
    }

    public function test_links_orphaned_event_members_rows_by_email(): void
    {
        $band  = Bands::factory()->create();
        $event = $this->makeEventForBand($band);

        $orphan = EventMember::create([
            'event_id' => $event->id,
            'band_id'  => $band->id,
            'user_id'  => null,
            'name'     => 'Pre-registration Sub',
            'email'    => 'latecomer@example.com',
        ]);

        $user = User::factory()->create(['email' => 'latecomer@example.com']);

        app(PendingInvitationService::class)->applyFor($user);

        $this->assertEquals($user->id, $orphan->fresh()->user_id);
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);
        $this->assertTrue($user->fresh()->hasRole('sub'));
    }

    public function test_links_orphaned_rehearsal_subs_rows_by_email(): void
    {
        $band      = Bands::factory()->create();
        $rehearsal = Rehearsal::factory()->create(['band_id' => $band->id]);

        $orphan = RehearsalSub::factory()->create([
            'rehearsal_id' => $rehearsal->id,
            'band_id'      => $band->id,
            'user_id'      => null,
            'email'        => 'rehearsal.sub@example.com',
        ]);

        $user = User::factory()->create(['email' => 'rehearsal.sub@example.com']);

        app(PendingInvitationService::class)->applyFor($user);

        $this->assertEquals($user->id, $orphan->fresh()->user_id);
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);
    }

    public function test_backfill_skips_rows_that_would_violate_the_unique_index(): void
    {
        $band  = Bands::factory()->create();
        $event = $this->makeEventForBand($band);
        $user  = User::factory()->create(['email' => 'dupe@example.com']);

        // A soft-deleted row still occupies the (event_id, user_id) unique index.
        EventMember::create([
            'event_id' => $event->id,
            'band_id'  => $band->id,
            'user_id'  => $user->id,
            'name'     => 'Old row',
        ])->delete();

        $orphan = EventMember::create([
            'event_id' => $event->id,
            'band_id'  => $band->id,
            'user_id'  => null,
            'name'     => 'Dupe Sub',
            'email'    => 'dupe@example.com',
        ]);

        app(PendingInvitationService::class)->applyFor($user);

        $this->assertNull($orphan->fresh()->user_id);
    }

    public function test_invitation_accepted_under_band_team_still_gets_global_sub_role(): void
    {
        $band = Bands::factory()->create();
        BandSubInvitation::factory()->create([
            'band_id' => $band->id,
            'email'   => 'team-scoped@example.com',
            'pending' => true,
        ]);
        $user = User::factory()->create(['email' => 'team-scoped@example.com']);

        // Simulate a caller with a band team active (e.g. web middleware) —
        // acceptBandInvitation() assigns `sub` under the ambient team, which
        // does not satisfy UserEventsService's team-0 hasRole('sub') check.
        \setPermissionsTeamId($band->id);
        app(PendingInvitationService::class)->applyFor($user);
        \setPermissionsTeamId(0);

        $user = $user->fresh();
        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole('sub'));
    }

    public function test_backfill_ignores_other_emails_and_assigns_no_role(): void
    {
        $band  = Bands::factory()->create();
        $event = $this->makeEventForBand($band);

        $orphan = EventMember::create([
            'event_id' => $event->id,
            'band_id'  => $band->id,
            'user_id'  => null,
            'name'     => 'Someone Else',
            'email'    => 'someone-else@example.com',
        ]);

        $user = User::factory()->create(['email' => 'me@example.com']);

        app(PendingInvitationService::class)->applyFor($user);

        $this->assertNull($orphan->fresh()->user_id);
        $this->assertFalse($user->fresh()->hasRole('sub'));
    }
}
