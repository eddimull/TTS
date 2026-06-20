<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use App\Services\GoogleCalendarService;
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

        // BandSettingsController injects BandMemberRemovalService which requires
        // GoogleCalendarService. Mock it so CI doesn't need the credentials file.
        $mock = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mock);

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
}
