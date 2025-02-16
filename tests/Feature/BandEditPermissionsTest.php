<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BandEditPermissionsTest extends TestCase
{

    public function test_unauthenticated_denial(): void
    {
        $band = Bands::factory()->hasOwner()->create();
        $response = $this->get("/bands/$band->id/edit");

        $response->assertRedirect('/login');
    }

    public function test_band_owner_can_edit(): void
    {
        $band = Bands::factory()->hasOwner()->create();
        $this->actingAs($band->owners->first()->user);
        $response = $this->get("/bands/$band->id/edit");

        $response->assertOk();
    }

    public function test_band_member_can_view(): void
    {
        $band = Bands::factory()->hasMember()->create();
        $bandMember = $band->members->first()->user;
        $response = $this->actingAs($bandMember)->get("/bands/$band->id/edit");

        $response->assertOk();
    }

    public function test_non_member_cannot_edit(): void
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get("/bands/$band->id/edit");

        $response->assertRedirect();
    }

    public function test_owner_can_update_band(): void
    {
        $band = Bands::factory()->hasOwner()->create();
        $owner = $band->owners->first()->user;
        $timestamp = Carbon::now()->timestamp;
        $response = $this->actingAs($owner)->patch("/bands/$band->id", [
            'name' => 'New Band Name',
            'site_name' => "site_name_$timestamp",
        ]);
        $response->assertRedirect("/bands");
        $this->assertDatabaseHas('bands', [
            'id' => $band->id,
            'name' => 'New Band Name',
            'site_name' => "site_name_$timestamp",
        ]);
    }

    public function test_member_cannot_update_band(): void
    {
        $band = Bands::factory()->hasMember()->create();
        $member = $band->members->first()->user;
        $response = $this->actingAs($member)->patch("/bands/$band->id", [
            'name' => 'New Band Name',
            'site_name' => 'site_name',
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('bands', [
            'id' => $band->id,
            'name' => 'New Band Name',
            'site_name' => 'site_name',
        ]);
    }


}
