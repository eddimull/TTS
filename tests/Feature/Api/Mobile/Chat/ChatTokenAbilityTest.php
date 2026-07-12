<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTokenAbilityTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_every_token_carries_the_chat_ability(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $sub   = $this->makeSubAssignedTo($band, $event);

        $service = app(TokenService::class);
        $this->assertContains('chat', $service->buildAbilities($owner));
        $this->assertContains('chat', $service->buildAbilities($sub));
    }

    public function test_members_endpoint_exposes_moderate_chat_for_granting(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);

        $response = $this->actingAs($owner)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/members")
            ->assertOk();

        $memberRow = collect($response->json('members'))->firstWhere('id', $member->id);
        $this->assertArrayHasKey('moderate:chat', $memberRow['permissions']);
        $this->assertFalse($memberRow['permissions']['moderate:chat']);

        $this->actingAs($owner)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson("/api/mobile/bands/{$band->id}/members/{$member->id}/permissions", [
                'permission' => 'moderate:chat', 'granted' => true,
            ])->assertOk();

        $this->assertTrue($member->fresh()->canModerateChat($band->id));
    }
}
