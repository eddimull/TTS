<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Invitations;
use App\Models\User;
use App\Services\PendingInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingInvitationServiceTest extends TestCase
{
    use RefreshDatabase;

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
