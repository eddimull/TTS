<?php

namespace Tests\Feature\Api\Mobile;

use App\Mail\BandSubInvitation as BandSubInvitationMail;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\BandRole;
use App\Models\Bands;
use App\Models\BandSubs;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\SubstituteCallList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the mobile substitute call list endpoints under /api/mobile:
 *
 *   GET    bands/{band}/call-lists
 *   POST   bands/{band}/call-lists
 *   POST   bands/{band}/call-lists/reorder
 *   PATCH  bands/{band}/call-lists/{callList}
 *   DELETE bands/{band}/call-lists/{callList}
 *
 * Owner-only: the `owner` middleware gates the band; the controller additionally
 * verifies the bound call-list entry belongs to the band (404 otherwise).
 */
class CallListMobileTest extends TestCase
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

        // Sub role + permissions are needed because adds trigger band invites.
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

    public function test_owner_can_list_call_lists_grouped_by_instrument(): void
    {
        SubstituteCallList::create([
            'band_id' => $this->band->id,
            'instrument' => 'Trumpet',
            'custom_name' => 'Sub One',
            'custom_email' => 'one@example.com',
            'custom_phone' => '555-0001',
            'priority' => 1,
        ]);
        SubstituteCallList::create([
            'band_id' => $this->band->id,
            'instrument' => 'Sax',
            'custom_name' => 'Sub Two',
            'custom_email' => 'two@example.com',
            'custom_phone' => '555-0002',
            'priority' => 1,
        ]);

        $response = $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/call-lists");

        $response->assertOk()
            ->assertJsonStructure(['call_lists' => ['Trumpet', 'Sax'], 'instruments'])
            ->assertJsonPath('call_lists.Trumpet.0.custom_name', 'Sub One')
            ->assertJsonPath('call_lists.Sax.0.custom_name', 'Sub Two');
    }

    public function test_member_cannot_list_call_lists(): void
    {
        $this->withHeaders($this->asMember())
            ->getJson("/api/mobile/bands/{$this->band->id}/call-lists")
            ->assertStatus(403);
    }

    // ── Store ────────────────────────────────────────────────────────────────

    public function test_owner_can_add_custom_person_and_invitation_is_sent(): void
    {
        Mail::fake();

        $response = $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/call-lists", [
                'instrument' => 'Trumpet',
                'custom_name' => 'Custom Sub',
                'custom_email' => 'customsub@example.com',
                'custom_phone' => '555-9999',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('entry.custom_name', 'Custom Sub');

        $this->assertDatabaseHas('substitute_call_lists', [
            'band_id' => $this->band->id,
            'custom_email' => 'customsub@example.com',
            'priority' => 1,
        ]);

        $this->assertDatabaseHas('band_sub_invitations', [
            'band_id' => $this->band->id,
            'email' => 'customsub@example.com',
            'name' => 'Custom Sub',
            'pending' => true,
        ]);

        Mail::assertSent(BandSubInvitationMail::class, fn ($mail) => $mail->hasTo('customsub@example.com'));
    }

    public function test_send_invite_false_suppresses_invitation_and_mail(): void
    {
        Mail::fake();

        $response = $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/call-lists", [
                'instrument' => 'Trumpet',
                'custom_name' => 'Quiet Sub',
                'custom_email' => 'quiet@example.com',
                'custom_phone' => '555-0000',
                'send_invite' => false,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseMissing('band_sub_invitations', [
            'band_id' => $this->band->id,
            'email' => 'quiet@example.com',
        ]);

        Mail::assertNotSent(BandSubInvitationMail::class);
    }

    public function test_store_requires_custom_fields_without_roster_member(): void
    {
        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/call-lists", ['instrument' => 'Trumpet'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_name', 'custom_email', 'custom_phone']);
    }

    public function test_member_cannot_add_to_call_list(): void
    {
        $this->withHeaders($this->asMember())
            ->postJson("/api/mobile/bands/{$this->band->id}/call-lists", [
                'instrument' => 'Trumpet',
                'custom_name' => 'Sneaky',
                'custom_email' => 'sneaky@example.com',
                'custom_phone' => '555-1111',
            ])
            ->assertStatus(403);
    }

    public function test_adding_roster_member_already_subbing_does_not_reinvite(): void
    {
        Mail::fake();

        $subUser = User::factory()->create(['email' => 'already@example.com']);
        BandSubs::create(['user_id' => $subUser->id, 'band_id' => $this->band->id]);

        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $roster->id,
            'user_id' => $subUser->id,
        ]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/call-lists", [
                'instrument' => 'Sax',
                'roster_member_id' => $rosterMember->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseMissing('band_sub_invitations', [
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,
        ]);
        Mail::assertNotSent(BandSubInvitationMail::class);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_owner_can_update_priority_and_notes(): void
    {
        $entry = SubstituteCallList::create([
            'band_id' => $this->band->id,
            'instrument' => 'Trumpet',
            'custom_name' => 'Sub',
            'custom_email' => 'sub@example.com',
            'custom_phone' => '555-0001',
            'priority' => 1,
        ]);

        $this->withHeaders($this->asOwner())
            ->patchJson("/api/mobile/bands/{$this->band->id}/call-lists/{$entry->id}", [
                'priority' => 3,
                'notes' => 'Prefers weekends',
            ])
            ->assertOk()
            ->assertJsonPath('entry.priority', 3);

        $this->assertDatabaseHas('substitute_call_lists', [
            'id' => $entry->id,
            'priority' => 3,
            'notes' => 'Prefers weekends',
        ]);
    }

    public function test_update_entry_from_another_band_returns_404(): void
    {
        $otherBand = Bands::factory()->create();
        $foreign = SubstituteCallList::create([
            'band_id' => $otherBand->id,
            'instrument' => 'Trumpet',
            'custom_name' => 'Foreign',
            'custom_email' => 'foreign@example.com',
            'custom_phone' => '555-0001',
            'priority' => 1,
        ]);

        $this->withHeaders($this->asOwner())
            ->patchJson("/api/mobile/bands/{$this->band->id}/call-lists/{$foreign->id}", ['priority' => 9])
            ->assertStatus(404);
    }

    // ── Destroy ──────────────────────────────────────────────────────────────

    public function test_owner_can_delete_call_list_entry(): void
    {
        $entry = SubstituteCallList::create([
            'band_id' => $this->band->id,
            'instrument' => 'Trumpet',
            'custom_name' => 'Sub',
            'custom_email' => 'sub@example.com',
            'custom_phone' => '555-0001',
            'priority' => 1,
        ]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/call-lists/{$entry->id}")
            ->assertOk();

        $this->assertDatabaseMissing('substitute_call_lists', ['id' => $entry->id]);
    }

    public function test_delete_entry_from_another_band_returns_404(): void
    {
        $otherBand = Bands::factory()->create();
        $foreign = SubstituteCallList::create([
            'band_id' => $otherBand->id,
            'instrument' => 'Trumpet',
            'custom_name' => 'Foreign',
            'custom_email' => 'foreign@example.com',
            'custom_phone' => '555-0001',
            'priority' => 1,
        ]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/call-lists/{$foreign->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('substitute_call_lists', ['id' => $foreign->id]);
    }

    // ── Reorder ──────────────────────────────────────────────────────────────

    public function test_owner_can_reorder_call_list(): void
    {
        $a = SubstituteCallList::create([
            'band_id' => $this->band->id, 'instrument' => 'Trumpet',
            'custom_name' => 'A', 'custom_email' => 'a@example.com', 'custom_phone' => '1', 'priority' => 1,
        ]);
        $b = SubstituteCallList::create([
            'band_id' => $this->band->id, 'instrument' => 'Trumpet',
            'custom_name' => 'B', 'custom_email' => 'b@example.com', 'custom_phone' => '2', 'priority' => 2,
        ]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/call-lists/reorder", [
                'instrument' => 'Trumpet',
                'order' => [$b->id, $a->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('substitute_call_lists', ['id' => $b->id, 'priority' => 1]);
        $this->assertDatabaseHas('substitute_call_lists', ['id' => $a->id, 'priority' => 2]);
    }
}
