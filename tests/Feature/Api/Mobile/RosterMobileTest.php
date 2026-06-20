<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\BandRole;
use App\Models\Bands;
use App\Models\Events;
use App\Models\EventMember;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\RosterSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the mobile roster system endpoints under /api/mobile:
 *
 *   bands/{band}/roles                      (band roles CRUD + reorder)
 *   bands/{band}/rosters                    (rosters CRUD + set-default + initialize)
 *   bands/{band}/rosters/{roster}/slots     (slot create)
 *   bands/{band}/roster-slots/{slot}        (slot update/delete)
 *   bands/{band}/rosters/{roster}/members   (member create)
 *   bands/{band}/roster-members/{member}    (member update/delete/toggle-active)
 *
 * Owner-only: the `owner` middleware gates the band; controllers additionally
 * verify the bound roster/slot/member/role belongs to the band (404 otherwise).
 */
class RosterMobileTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected User $outsider;
    protected Bands $band;
    protected string $ownerToken;
    protected string $memberToken;
    protected string $outsiderToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->band = Bands::factory()->create();

        BandOwners::create(['band_id' => $this->band->id, 'user_id' => $this->owner->id]);
        BandMembers::create(['band_id' => $this->band->id, 'user_id' => $this->member->id]);

        $this->ownerToken = $this->owner->createToken('test-device')->plainTextToken;
        $this->memberToken = $this->member->createToken('test-device')->plainTextToken;
        $this->outsiderToken = $this->outsider->createToken('test-device')->plainTextToken;
    }

    private function headers(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $this->band->id,
            'Accept'        => 'application/json',
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

    // ── Band roles ─────────────────────────────────────────────────────────

    public function test_owner_can_list_roles_with_counts(): void
    {
        // The BandObserver seeds 8 default roles on band creation
        // (Vocals, Guitar, Bass, Drums, Keys, Saxophone, Trumpet, Trombone).
        BandRole::create(['band_id' => $this->band->id, 'name' => 'Banjo', 'display_order' => 8]);

        $response = $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/roles");

        $response->assertOk()
            ->assertJsonCount(9, 'roles')
            ->assertJsonFragment(['name' => 'Banjo'])
            ->assertJsonStructure([
                'roles' => [['id', 'name', 'roster_members_count', 'event_members_count', 'substitute_call_lists_count']],
            ]);
    }

    public function test_member_cannot_list_roles(): void
    {
        $this->withHeaders($this->asMember())
            ->getJson("/api/mobile/bands/{$this->band->id}/roles")
            ->assertStatus(403);
    }

    public function test_owner_can_create_role(): void
    {
        $response = $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/roles", ['name' => 'Banjo']);

        $response->assertStatus(201)
            ->assertJsonPath('role.name', 'Banjo');

        $this->assertDatabaseHas('band_roles', [
            'band_id' => $this->band->id,
            'name' => 'Banjo',
        ]);
    }

    public function test_create_role_requires_name(): void
    {
        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/roles", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_owner_can_update_role(): void
    {
        $role = BandRole::create(['band_id' => $this->band->id, 'name' => 'Banjo', 'display_order' => 8]);

        $this->withHeaders($this->asOwner())
            ->patchJson("/api/mobile/bands/{$this->band->id}/roles/{$role->id}", ['name' => 'Mandolin'])
            ->assertOk()
            ->assertJsonPath('role.name', 'Mandolin');
    }

    public function test_owner_can_deactivate_role_via_destroy(): void
    {
        $role = BandRole::create(['band_id' => $this->band->id, 'name' => 'Sax', 'display_order' => 0, 'is_active' => true]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/roles/{$role->id}")
            ->assertOk();

        $this->assertDatabaseHas('band_roles', ['id' => $role->id, 'is_active' => false]);
    }

    public function test_owner_can_reorder_roles(): void
    {
        $a = BandRole::create(['band_id' => $this->band->id, 'name' => 'A', 'display_order' => 0]);
        $b = BandRole::create(['band_id' => $this->band->id, 'name' => 'B', 'display_order' => 1]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/roles/reorder", [
                'roles' => [
                    ['id' => $a->id, 'display_order' => 5],
                    ['id' => $b->id, 'display_order' => 2],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('band_roles', ['id' => $a->id, 'display_order' => 5]);
        $this->assertDatabaseHas('band_roles', ['id' => $b->id, 'display_order' => 2]);
    }

    public function test_role_from_another_band_returns_404(): void
    {
        $otherBand = Bands::factory()->create();
        $foreignRole = BandRole::create(['band_id' => $otherBand->id, 'name' => 'Foreign', 'display_order' => 0]);

        $this->withHeaders($this->asOwner())
            ->patchJson("/api/mobile/bands/{$this->band->id}/roles/{$foreignRole->id}", ['name' => 'Hijack'])
            ->assertStatus(404);
    }

    // ── Rosters ──────────────────────────────────────────────────────────────

    public function test_owner_can_list_rosters_sorted_default_then_name(): void
    {
        Roster::factory()->create(['band_id' => $this->band->id, 'name' => 'Z Roster', 'is_default' => false]);
        Roster::factory()->create(['band_id' => $this->band->id, 'name' => 'A Roster', 'is_default' => false]);
        Roster::factory()->create(['band_id' => $this->band->id, 'name' => 'Default', 'is_default' => true]);

        $response = $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/rosters");

        $response->assertOk();
        $rosters = $response->json('rosters');
        $this->assertSame('Default', $rosters[0]['name']);
        $this->assertTrue($rosters[0]['is_default']);
        $this->assertSame('A Roster', $rosters[1]['name']);
        $this->assertSame('Z Roster', $rosters[2]['name']);
    }

    public function test_member_cannot_list_rosters(): void
    {
        Roster::factory()->create(['band_id' => $this->band->id]);

        $this->withHeaders($this->asMember())
            ->getJson("/api/mobile/bands/{$this->band->id}/rosters")
            ->assertStatus(403);
    }

    public function test_owner_can_create_roster(): void
    {
        $response = $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters", [
                'name' => 'New Roster',
                'description' => 'A test roster',
                'is_default' => false,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Roster']);

        $this->assertDatabaseHas('rosters', ['band_id' => $this->band->id, 'name' => 'New Roster']);
    }

    public function test_create_roster_requires_name(): void
    {
        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters", ['description' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_owner_can_view_roster_with_slots_and_members(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        RosterSlot::create(['roster_id' => $roster->id, 'name' => 'Lead', 'is_required' => true, 'quantity' => 1]);
        RosterMember::factory()->create(['roster_id' => $roster->id, 'user_id' => $this->member->id]);

        $response = $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'slots')
            ->assertJsonCount(1, 'members')
            ->assertJsonPath('slots.0.name', 'Lead')
            ->assertJsonPath('members.0.user_id', $this->member->id);
    }

    public function test_owner_can_update_roster(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);

        $this->withHeaders($this->asOwner())
            ->patchJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}", ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated']);

        $this->assertDatabaseHas('rosters', ['id' => $roster->id, 'name' => 'Updated']);
    }

    public function test_owner_can_delete_roster(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id, 'is_default' => false]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}")
            ->assertOk();

        $this->assertSoftDeleted('rosters', ['id' => $roster->id]);
    }

    public function test_cannot_delete_default_roster(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id, 'is_default' => true]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete the default roster']);

        $this->assertDatabaseHas('rosters', ['id' => $roster->id, 'deleted_at' => null]);
    }

    public function test_cannot_delete_roster_with_events(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id, 'is_default' => false]);
        Events::factory()->create(['roster_id' => $roster->id]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete roster that is assigned to events']);
    }

    public function test_owner_can_set_roster_default(): void
    {
        $roster1 = Roster::factory()->create(['band_id' => $this->band->id, 'is_default' => true]);
        $roster2 = Roster::factory()->create(['band_id' => $this->band->id, 'is_default' => false]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster2->id}/set-default")
            ->assertOk();

        $this->assertDatabaseHas('rosters', ['id' => $roster2->id, 'is_default' => true]);
        $this->assertDatabaseHas('rosters', ['id' => $roster1->id, 'is_default' => false]);
    }

    public function test_owner_can_initialize_default_roster_from_band(): void
    {
        $response = $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/initialize");

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Default Roster']);

        $this->assertDatabaseHas('rosters', ['band_id' => $this->band->id, 'is_default' => true]);

        $roster = Roster::where('band_id', $this->band->id)->first();
        $this->assertDatabaseHas('roster_members', ['roster_id' => $roster->id, 'user_id' => $this->owner->id]);
        $this->assertDatabaseHas('roster_members', ['roster_id' => $roster->id, 'user_id' => $this->member->id]);
    }

    public function test_cannot_initialize_when_default_exists(): void
    {
        Roster::factory()->create(['band_id' => $this->band->id, 'is_default' => true]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/initialize")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Band already has a default roster']);
    }

    public function test_roster_from_another_band_returns_404(): void
    {
        $otherBand = Bands::factory()->create();
        $foreign = Roster::factory()->create(['band_id' => $otherBand->id]);

        $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/rosters/{$foreign->id}")
            ->assertStatus(404);
    }

    // ── Roster slots ───────────────────────────────────────────────────────

    public function test_owner_can_create_slot(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}/slots", [
                'name' => 'Lead Vocal',
                'is_required' => true,
                'quantity' => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('slot.name', 'Lead Vocal');

        $this->assertDatabaseHas('roster_slots', ['roster_id' => $roster->id, 'name' => 'Lead Vocal']);
    }

    public function test_member_cannot_create_slot(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);

        $this->withHeaders($this->asMember())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}/slots", ['name' => 'Sneaky'])
            ->assertStatus(403);
    }

    public function test_owner_can_update_slot(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $slot = RosterSlot::create(['roster_id' => $roster->id, 'name' => 'Old', 'is_required' => true, 'quantity' => 1]);

        $this->withHeaders($this->asOwner())
            ->patchJson("/api/mobile/bands/{$this->band->id}/roster-slots/{$slot->id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonPath('slot.name', 'New');
    }

    public function test_owner_can_delete_slot(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $slot = RosterSlot::create(['roster_id' => $roster->id, 'name' => 'Gone', 'is_required' => true, 'quantity' => 1]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/roster-slots/{$slot->id}")
            ->assertOk();

        $this->assertDatabaseMissing('roster_slots', ['id' => $slot->id]);
    }

    public function test_slot_from_another_band_returns_404(): void
    {
        $otherBand = Bands::factory()->create();
        $otherRoster = Roster::factory()->create(['band_id' => $otherBand->id]);
        $foreignSlot = RosterSlot::create(['roster_id' => $otherRoster->id, 'name' => 'Foreign', 'is_required' => true, 'quantity' => 1]);

        $this->withHeaders($this->asOwner())
            ->patchJson("/api/mobile/bands/{$this->band->id}/roster-slots/{$foreignSlot->id}", ['name' => 'Hijack'])
            ->assertStatus(404);
    }

    // ── Roster members ─────────────────────────────────────────────────────

    public function test_owner_can_add_user_to_roster(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $newUser = User::factory()->create();

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}/members", [
                'user_id' => $newUser->id,
                'role' => 'Guitar',
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['user_id' => $newUser->id]);

        $this->assertDatabaseHas('roster_members', [
            'roster_id' => $roster->id,
            'user_id' => $newUser->id,
            'role' => 'Guitar',
        ]);
    }

    public function test_owner_can_add_non_user_to_roster(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}/members", [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '555-1234',
                'role' => 'Bass',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('roster_members', [
            'roster_id' => $roster->id,
            'user_id' => null,
            'name' => 'John Doe',
        ]);
    }

    public function test_member_cannot_add_member(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $newUser = User::factory()->create();

        $this->withHeaders($this->asMember())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}/members", ['user_id' => $newUser->id])
            ->assertStatus(403);
    }

    public function test_add_member_requires_name_without_user_id(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}/members", ['email' => 'x@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_cannot_add_same_user_twice(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        RosterMember::factory()->create(['roster_id' => $roster->id, 'user_id' => $this->member->id]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$roster->id}/members", ['user_id' => $this->member->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_owner_can_update_member(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $roster->id,
            'user_id' => $this->member->id,
            'role' => 'Guitar',
        ]);

        $this->withHeaders($this->asOwner())
            ->patchJson("/api/mobile/bands/{$this->band->id}/roster-members/{$rosterMember->id}", ['role' => 'Bass'])
            ->assertOk();

        $this->assertDatabaseHas('roster_members', ['id' => $rosterMember->id, 'role' => 'Bass']);
    }

    public function test_owner_can_delete_member_without_history(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->create(['roster_id' => $roster->id]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/roster-members/{$rosterMember->id}")
            ->assertOk();

        $this->assertDatabaseMissing('roster_members', ['id' => $rosterMember->id]);
    }

    public function test_member_with_history_is_soft_deleted(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->create(['roster_id' => $roster->id]);
        EventMember::factory()->create(['roster_member_id' => $rosterMember->id]);

        $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/roster-members/{$rosterMember->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Roster member removed (archived due to attendance history)']);

        $this->assertSoftDeleted('roster_members', ['id' => $rosterMember->id]);
    }

    public function test_member_cannot_delete_member(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->create(['roster_id' => $roster->id]);

        $this->withHeaders($this->asMember())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/roster-members/{$rosterMember->id}")
            ->assertStatus(403);
    }

    public function test_owner_can_toggle_member_active(): void
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->create(['roster_id' => $roster->id, 'is_active' => true]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/roster-members/{$rosterMember->id}/toggle-active")
            ->assertOk()
            ->assertJsonFragment(['is_active' => false]);

        $this->assertDatabaseHas('roster_members', ['id' => $rosterMember->id, 'is_active' => false]);
    }

    public function test_member_from_another_band_returns_404(): void
    {
        $otherBand = Bands::factory()->create();
        $otherRoster = Roster::factory()->create(['band_id' => $otherBand->id]);
        $foreign = RosterMember::factory()->create(['roster_id' => $otherRoster->id]);

        $this->withHeaders($this->asOwner())
            ->postJson("/api/mobile/bands/{$this->band->id}/roster-members/{$foreign->id}/toggle-active")
            ->assertStatus(404);
    }
}
