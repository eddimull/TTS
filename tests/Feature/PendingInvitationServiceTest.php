<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\BandSubInvitation;
use App\Models\Invitations;
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
}
