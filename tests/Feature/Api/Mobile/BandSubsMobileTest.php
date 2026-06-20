<?php

namespace Tests\Feature\Api\Mobile;

use App\Mail\BandSubInvitation as BandSubInvitationMail;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\BandRole;
use App\Models\Bands;
use App\Models\BandSubInvitation;
use App\Models\BandSubs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the mobile band subs endpoints under /api/mobile:
 *
 *   GET    bands/{band}/subs
 *   POST   bands/{band}/subs/invite
 *   DELETE bands/{band}/subs/invitations/{invitation}
 *   DELETE bands/{band}/subs/{user}
 *
 * Owner-only: the `owner` middleware gates the band; the controller additionally
 * verifies the bound invitation / band-sub link belongs to the band (404 otherwise).
 */
class BandSubsMobileTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected Bands $band;
    protected string $ownerToken;
    protected string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);
        \setPermissionsTeamId(0);

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();

        $this->band = Bands::factory()->create();

        BandOwners::create(['band_id' => $this->band->id, 'user_id' => $this->owner->id]);
        BandMembers::create(['band_id' => $this->band->id, 'user_id' => $this->member->id]);

        $this->ownerToken = $this->owner->createToken('test-device')->plainTextToken;
        $this->memberToken = $this->member->createToken('test-device')->plainTextToken;
    }

    private function headers(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    private function asOwner(): array
    {
        return $this->headers($this->ownerToken);
    }

    private function asMember(): array
    {
        return $this->headers($this->memberToken);
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_owner_can_list_active_and_pending_subs(): void
    {
        $activeUser = User::factory()->create(['name' => 'Active Sub', 'email' => 'active@example.com']);
        BandSubs::create(['user_id' => $activeUser->id, 'band_id' => $this->band->id]);

        BandSubInvitation::create([
            'band_id' => $this->band->id,
            'email' => 'pending@example.com',
            'name' => 'Pending Sub',
            'pending' => true,
        ]);

        $response = $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/subs");

        $response->assertOk()
            ->assertJsonCount(2, 'subs')
            ->assertJsonFragment(['status' => 'active', 'email' => 'active@example.com'])
            ->assertJsonFragment(['status' => 'pending', 'email' => 'pending@example.com']);
    }

    public function test_member_cannot_list_subs(): void
    {
        $this->withHeaders($this->asMember())
            ->getJson("/api/mobile/bands/{$this->band->id}/subs")
            ->assertStatus(403);
    }

    // ── Invite ───────────────────────────────────────────────────────────────

    public function test_owner_can_invite_a_band_sub(): void
    {
        Mail::fake();

        $response = $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/subs/invite", [
                'email' => 'newsub@example.com',
                'name' => 'New Sub',
                'phone' => '555-1234',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('invitation.email', 'newsub@example.com')
            ->assertJsonPath('invitation.status', 'pending');

        $this->assertDatabaseHas('band_sub_invitations', [
            'band_id' => $this->band->id,
            'email' => 'newsub@example.com',
            'name' => 'New Sub',
            'pending' => true,
        ]);

        Mail::assertSent(BandSubInvitationMail::class, fn ($mail) => $mail->hasTo('newsub@example.com'));
    }

    public function test_invite_requires_email(): void
    {
        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/subs/invite", ['name' => 'No Email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_member_cannot_invite(): void
    {
        $this->withHeaders($this->asMember())
            ->postJson("/api/mobile/bands/{$this->band->id}/subs/invite", ['email' => 'x@example.com'])
            ->assertStatus(403);
    }

    // ── Revoke pending invitation ─────────────────────────────────────────────

    public function test_owner_can_revoke_pending_invitation(): void
    {
        $invitation = BandSubInvitation::create([
            'band_id' => $this->band->id,
            'email' => 'revoke@example.com',
            'pending' => true,
        ]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/subs/invitations/{$invitation->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('band_sub_invitations', ['id' => $invitation->id]);
    }

    public function test_revoke_invitation_from_another_band_returns_404(): void
    {
        $otherBand = Bands::factory()->create();
        $foreign = BandSubInvitation::create([
            'band_id' => $otherBand->id,
            'email' => 'foreign@example.com',
            'pending' => true,
        ]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/subs/invitations/{$foreign->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('band_sub_invitations', ['id' => $foreign->id]);
    }

    // ── Remove active sub ─────────────────────────────────────────────────────

    public function test_owner_can_remove_active_sub(): void
    {
        $subUser = User::factory()->create();
        BandSubs::create(['user_id' => $subUser->id, 'band_id' => $this->band->id]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/subs/{$subUser->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('band_subs', [
            'user_id' => $subUser->id,
            'band_id' => $this->band->id,
        ]);
    }

    public function test_remove_sub_from_another_band_returns_404(): void
    {
        $otherBand = Bands::factory()->create();
        $subUser = User::factory()->create();
        BandSubs::create(['user_id' => $subUser->id, 'band_id' => $otherBand->id]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/subs/{$subUser->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('band_subs', [
            'user_id' => $subUser->id,
            'band_id' => $otherBand->id,
        ]);
    }
}
