<?php

namespace Tests\Unit\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenServiceQuestionnairesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_token_includes_questionnaires_abilities(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        $abilities = app(TokenService::class)->buildAbilities($user->fresh());

        $this->assertContains('read:questionnaires', $abilities);
        $this->assertContains('write:questionnaires', $abilities);
    }
}
