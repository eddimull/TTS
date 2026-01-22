<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\BandMembers;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\User;
use App\Models\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RosterManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected User $outsider;
    protected Bands $band;

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
    }

    #[Test]
    public function owner_can_list_all_rosters_for_their_band()
    {
        $roster1 = Roster::factory()->create(['band_id' => $this->band->id, 'name' => 'Full Band']);
        $roster2 = Roster::factory()->create(['band_id' => $this->band->id, 'name' => 'Acoustic Trio']);

        $response = $this->actingAs($this->owner)
            ->getJson("/bands/{$this->band->id}/rosters");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'rosters')
            ->assertJsonFragment(['name' => 'Full Band'])
            ->assertJsonFragment(['name' => 'Acoustic Trio']);
    }

    #[Test]
    public function member_can_list_rosters_for_their_band()
    {
        Roster::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->member)
            ->getJson("/bands/{$this->band->id}/rosters");

        $response->assertStatus(200)
            ->assertJsonStructure(['rosters']);
    }

    #[Test]
    public function outsider_cannot_list_rosters_for_band()
    {
        $response = $this->actingAs($this->outsider)
            ->getJson("/bands/{$this->band->id}/rosters");

        $response->assertStatus(403);
    }

    #[Test]
    public function owner_can_create_roster()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/bands/{$this->band->id}/rosters", [
                'name' => 'New Roster',
                'description' => 'A test roster',
                'is_default' => false,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'New Roster',
                'description' => 'A test roster',
            ]);

        $this->assertDatabaseHas('rosters', [
            'band_id' => $this->band->id,
            'name' => 'New Roster',
        ]);
    }

    #[Test]
    public function member_cannot_create_roster()
    {
        $response = $this->actingAs($this->member)
            ->postJson("/bands/{$this->band->id}/rosters", [
                'name' => 'New Roster',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function roster_name_is_required()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/bands/{$this->band->id}/rosters", [
                'description' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function owner_can_view_roster_with_members()
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $roster->id,
            'user_id' => $this->member->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/rosters/{$roster->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $roster->name])
            ->assertJsonCount(1, 'members');
    }

    #[Test]
    public function owner_can_update_roster()
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)
            ->patchJson("/rosters/{$roster->id}", [
                'name' => 'Updated Name',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('rosters', [
            'id' => $roster->id,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function member_cannot_update_roster()
    {
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->member)
            ->patchJson("/rosters/{$roster->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function owner_can_delete_roster()
    {
        $roster = Roster::factory()->create([
            'band_id' => $this->band->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/rosters/{$roster->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('rosters', ['id' => $roster->id]);
    }

    #[Test]
    public function cannot_delete_default_roster()
    {
        $roster = Roster::factory()->create([
            'band_id' => $this->band->id,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/rosters/{$roster->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete the default roster']);

        $this->assertDatabaseHas('rosters', ['id' => $roster->id]);
    }

    #[Test]
    public function cannot_delete_roster_with_events()
    {
        $roster = Roster::factory()->create([
            'band_id' => $this->band->id,
            'is_default' => false,
        ]);

        // Create an event using this roster
        Events::factory()->create(['roster_id' => $roster->id]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/rosters/{$roster->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete roster that is assigned to events']);
    }

    #[Test]
    public function owner_can_set_roster_as_default()
    {
        $roster1 = Roster::factory()->create([
            'band_id' => $this->band->id,
            'is_default' => true,
        ]);

        $roster2 = Roster::factory()->create([
            'band_id' => $this->band->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/bands/{$this->band->id}/rosters/{$roster2->id}/set-default");

        $response->assertStatus(200);

        $this->assertDatabaseHas('rosters', [
            'id' => $roster2->id,
            'is_default' => true,
        ]);

        // Verify old default was unset
        $this->assertDatabaseHas('rosters', [
            'id' => $roster1->id,
            'is_default' => false,
        ]);
    }

    #[Test]
    public function owner_can_initialize_default_roster_from_band()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/bands/{$this->band->id}/rosters/initialize");

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Default Roster']);

        // Verify roster was created
        $this->assertDatabaseHas('rosters', [
            'band_id' => $this->band->id,
            'is_default' => true,
        ]);

        // Verify owner and member were added
        $roster = Roster::where('band_id', $this->band->id)->first();
        $this->assertDatabaseHas('roster_members', [
            'roster_id' => $roster->id,
            'user_id' => $this->owner->id,
        ]);
        $this->assertDatabaseHas('roster_members', [
            'roster_id' => $roster->id,
            'user_id' => $this->member->id,
        ]);
    }

    #[Test]
    public function cannot_initialize_roster_if_default_already_exists()
    {
        Roster::factory()->create([
            'band_id' => $this->band->id,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/bands/{$this->band->id}/rosters/initialize");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Band already has a default roster']);
    }

    #[Test]
    public function rosters_are_sorted_by_default_then_name()
    {
        Roster::factory()->create(['band_id' => $this->band->id, 'name' => 'Z Roster', 'is_default' => false]);
        Roster::factory()->create(['band_id' => $this->band->id, 'name' => 'A Roster', 'is_default' => false]);
        Roster::factory()->create(['band_id' => $this->band->id, 'name' => 'Default', 'is_default' => true]);

        $response = $this->actingAs($this->owner)
            ->getJson("/bands/{$this->band->id}/rosters");

        $response->assertStatus(200);

        $rosters = $response->json('rosters');

        // Default should be first
        $this->assertEquals('Default', $rosters[0]['name']);
        $this->assertTrue($rosters[0]['is_default']);

        // Others should be alphabetical
        $this->assertEquals('A Roster', $rosters[1]['name']);
        $this->assertEquals('Z Roster', $rosters[2]['name']);
    }
}
