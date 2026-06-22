<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Events;
use App\Models\EventMember;
use App\Models\GoogleDriveConnection;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\GoogleDriveOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Covers the mobile band members listing under /api/mobile/bands/{band}/members
 * (BandSettingsController@members), specifically that each member carries an
 * email so the mobile app can surface email/message/copy contact actions.
 */
class BandMembersMobileTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected Bands $band;
    protected string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);
        \setPermissionsTeamId(0);

        // BandSettingsController injects BandMemberRemovalService, which depends on
        // GoogleCalendarService and GoogleDriveOAuthService. Mock both so CI doesn't
        // need real credentials or make network calls during removal.
        $mock = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $driveMock = Mockery::mock(GoogleDriveOAuthService::class);
        $driveMock->shouldReceive('revokeToken')->andReturnTrue();
        $this->app->instance(GoogleDriveOAuthService::class, $driveMock);

        $this->owner = User::factory()->create([
            'name' => 'Owner Person',
            'email' => 'owner@example.com',
        ]);
        $this->member = User::factory()->create([
            'name' => 'Member Person',
            'email' => 'member@example.com',
        ]);

        $this->band = Bands::factory()->create();

        BandOwners::create(['band_id' => $this->band->id, 'user_id' => $this->owner->id]);
        BandMembers::create(['band_id' => $this->band->id, 'user_id' => $this->member->id]);

        $this->ownerToken = $this->owner->createToken('test-device')->plainTextToken;
    }

    private function asOwner(): array
    {
        return [
            'Authorization' => "Bearer {$this->ownerToken}",
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    public function test_members_listing_includes_email(): void
    {
        $response = $this->withHeaders($this->asOwner())
            ->getJson("/api/mobile/bands/{$this->band->id}/members");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Owner Person', 'email' => 'owner@example.com', 'is_owner' => true])
            ->assertJsonFragment(['name' => 'Member Person', 'email' => 'member@example.com', 'is_owner' => false]);
    }

    public function test_remove_member_runs_full_cleanup_via_mobile_endpoint(): void
    {
        // Future event the member is on, and a Google Drive connection they set up —
        // both should be cleaned when removed through the mobile endpoint.
        $futureEvent = Events::factory()->forBand($this->band)->create(['date' => now()->addMonth()]);
        EventMember::factory()->create([
            'event_id' => $futureEvent->id,
            'band_id'  => $this->band->id,
            'user_id'  => $this->member->id,
        ]);

        $connection = GoogleDriveConnection::create([
            'user_id'              => $this->member->id,
            'band_id'              => $this->band->id,
            'access_token'         => 'fake-access-token',
            'refresh_token'        => 'fake-refresh-token',
            'google_account_email' => $this->member->email,
            'is_active'            => true,
        ]);

        $response = $this->withHeaders($this->asOwner())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/members/{$this->member->id}");

        $response->assertNoContent();

        // Member is gone from the band.
        $this->assertDatabaseMissing('band_members', [
            'band_id' => $this->band->id,
            'user_id' => $this->member->id,
        ]);

        // Future event assignment removed and Drive connection disconnected.
        $this->assertDatabaseMissing('event_members', [
            'event_id'   => $futureEvent->id,
            'user_id'    => $this->member->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('google_drive_connections', ['id' => $connection->id]);
    }
}
