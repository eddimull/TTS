<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Services\GoogleCalendarService;
use App\Services\GoogleDriveOAuthService;
use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatTokenAbilityTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        // BandSettingsController injects BandMemberRemovalService, which depends on
        // GoogleCalendarService and GoogleDriveOAuthService. Mock both so CI doesn't
        // need real credentials or make network calls during removal.
        $mock = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $driveMock = Mockery::mock(GoogleDriveOAuthService::class);
        $driveMock->shouldReceive('revokeToken')->andReturnTrue();
        $this->app->instance(GoogleDriveOAuthService::class, $driveMock);
    }

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
