<?php

namespace Tests\Feature\Web\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class ModerateChatPermissionPageTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_permissions_page_exposes_and_round_trips_moderate_chat(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band, ['read:events']);

        $this->actingAs($owner)
            ->get("/permissions/{$band->id}/{$member->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Band/ShowPermissions')
                ->where('permissions.moderate:chat', false)
                ->where('permissions.read:events', true));

        $this->actingAs($owner)
            ->post("/permissions/{$band->id}/{$member->id}", [
                'permissions' => ['read:events' => true, 'moderate:chat' => true],
            ])
            ->assertRedirect();

        $this->assertTrue($member->fresh()->canModerateChat($band->id));

        $this->actingAs($owner)
            ->post("/permissions/{$band->id}/{$member->id}", [
                'permissions' => ['read:events' => true],
            ])
            ->assertRedirect();

        $this->assertFalse($member->fresh()->canModerateChat($band->id));
    }
}
