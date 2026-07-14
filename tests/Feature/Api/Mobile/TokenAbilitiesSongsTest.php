<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\Bands;
use App\Models\User;
use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenAbilitiesSongsTest extends TestCase
{
    use RefreshDatabase;

    public function test_band_owner_token_includes_song_abilities(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $abilities = (new TokenService())->buildAbilities($user->fresh());

        $this->assertContains('read:songs', $abilities);
        $this->assertContains('write:songs', $abilities);
    }

    public function test_member_with_read_only_gets_read_but_not_write(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandMembers::create(['band_id' => $band->id, 'user_id' => $user->id]);
        setPermissionsTeamId($band->id);
        $user->givePermissionTo('read:songs');
        setPermissionsTeamId(0);

        $abilities = (new TokenService())->buildAbilities($user->fresh());

        $this->assertContains('read:songs', $abilities);
        $this->assertNotContains('write:songs', $abilities);
    }
}
