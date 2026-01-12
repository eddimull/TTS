<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\BandMembers;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\EventMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RosterMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected User $outsider;
    protected Bands $band;
    protected Roster $roster;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        // Create band
        $this->band = Bands::factory()->create();

        // Add owner and member
        BandOwners::create(['band_id' => $this->band->id, 'user_id' => $this->owner->id]);
        BandMembers::create(['band_id' => $this->band->id, 'user_id' => $this->member->id]);

        // Create a roster
        $this->roster = Roster::factory()->create(['band_id' => $this->band->id]);
    }

    #[Test]
    public function owner_can_add_user_to_roster()
    {
        $newUser = User::factory()->create();

        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => $newUser->id,
                'role' => 'Guitar',
                'default_payout_type' => 'equal_split',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['user_id' => $newUser->id]);

        $this->assertDatabaseHas('roster_members', [
            'roster_id' => $this->roster->id,
            'user_id' => $newUser->id,
            'role' => 'Guitar',
        ]);
    }

    #[Test]
    public function owner_can_add_non_user_to_roster()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '555-1234',
                'role' => 'Bass',
                'default_payout_type' => 'fixed',
                'default_payout_amount' => 150.00,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('roster_members', [
            'roster_id' => $this->roster->id,
            'user_id' => null,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'default_payout_amount' => 15000, // cents
        ]);
    }

    #[Test]
    public function member_cannot_add_member_to_roster()
    {
        $newUser = User::factory()->create();

        $response = $this->actingAs($this->member)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => $newUser->id,
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function name_is_required_when_no_user_id()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'email' => 'test@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function user_id_must_exist()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    #[Test]
    public function cannot_add_same_user_to_roster_twice()
    {
        RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'user_id' => $this->member->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => $this->member->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    #[Test]
    public function payout_type_must_be_valid()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => $this->member->id,
                'default_payout_type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_payout_type']);
    }

    #[Test]
    public function payout_amount_is_converted_to_cents()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => $this->member->id,
                'default_payout_type' => 'fixed',
                'default_payout_amount' => 250.50,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('roster_members', [
            'user_id' => $this->member->id,
            'default_payout_amount' => 25050, // 250.50 * 100
        ]);
    }

    #[Test]
    public function owner_can_update_roster_member()
    {
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'user_id' => $this->member->id,
            'role' => 'Guitar',
        ]);

        $response = $this->actingAs($this->owner)
            ->patchJson("/roster-members/{$rosterMember->id}", [
                'role' => 'Bass',
                'default_payout_type' => 'percentage',
                'default_payout_amount' => 100.00,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('roster_members', [
            'id' => $rosterMember->id,
            'role' => 'Bass',
            'default_payout_type' => 'percentage',
            'default_payout_amount' => 10000,
        ]);
    }

    #[Test]
    public function member_cannot_update_roster_member()
    {
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
        ]);

        $response = $this->actingAs($this->member)
            ->patchJson("/roster-members/{$rosterMember->id}", [
                'role' => 'Bass',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function owner_can_delete_roster_member_without_history()
    {
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/roster-members/{$rosterMember->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('roster_members', [
            'id' => $rosterMember->id,
        ]);
    }

    #[Test]
    public function roster_member_with_attendance_history_is_soft_deleted()
    {
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
        ]);

        // Create attendance history
        EventMember::factory()->create([
            'roster_member_id' => $rosterMember->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/roster-members/{$rosterMember->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Roster member removed (archived due to attendance history)']);

        // Should be soft deleted
        $this->assertSoftDeleted('roster_members', [
            'id' => $rosterMember->id,
        ]);
    }

    #[Test]
    public function member_cannot_delete_roster_member()
    {
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
        ]);

        $response = $this->actingAs($this->member)
            ->deleteJson("/roster-members/{$rosterMember->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function owner_can_toggle_roster_member_active_status()
    {
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/roster-members/{$rosterMember->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJsonFragment(['is_active' => false]);

        $this->assertDatabaseHas('roster_members', [
            'id' => $rosterMember->id,
            'is_active' => false,
        ]);

        // Toggle back
        $response = $this->actingAs($this->owner)
            ->postJson("/roster-members/{$rosterMember->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJsonFragment(['is_active' => true]);
    }

    #[Test]
    public function email_must_be_valid_format()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'name' => 'John Doe',
                'email' => 'not-an-email',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function payout_amount_cannot_be_negative()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => $this->member->id,
                'default_payout_amount' => -100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_payout_amount']);
    }

    #[Test]
    public function notes_can_be_added_to_roster_member()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => $this->member->id,
                'notes' => 'Excellent musician',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('roster_members', [
            'user_id' => $this->member->id,
            'notes' => 'Excellent musician',
        ]);
    }

    #[Test]
    public function display_name_uses_user_name_when_available()
    {
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'user_id' => $this->member->id,
        ]);

        $this->assertEquals($this->member->name, $rosterMember->display_name);
    }

    #[Test]
    public function display_name_uses_name_field_for_non_users()
    {
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'user_id' => null,
            'name' => 'John Doe',
        ]);

        $this->assertEquals('John Doe', $rosterMember->display_name);
    }

    #[Test]
    public function can_filter_roster_members_by_active_status()
    {
        RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'is_active' => true,
        ]);

        RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'is_active' => false,
        ]);

        $activeMembers = RosterMember::active()->get();

        $this->assertCount(1, $activeMembers);
        $this->assertTrue($activeMembers->first()->is_active);
    }

    #[Test]
    public function can_differentiate_between_users_and_non_users()
    {
        $userMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'user_id' => $this->member->id,
        ]);

        $nonUserMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'user_id' => null,
            'name' => 'External Sub',
        ]);

        $this->assertTrue($userMember->isUser());
        $this->assertFalse($userMember->isNonUser());

        $this->assertFalse($nonUserMember->isUser());
        $this->assertTrue($nonUserMember->isNonUser());
    }
}
