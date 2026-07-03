<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Invitations;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PendingInvitationService;
use App\Services\SocialAuth\SocialAuthService;
use App\Services\SocialAuth\SocialProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $overrides = []): SocialProfile
    {
        return new SocialProfile(...array_merge([
            'provider'   => 'google',
            'providerId' => 'g-1',
            'email'      => 'new@example.com',
            'name'       => 'New Person',
            'avatarUrl'  => 'https://example.com/a.png',
        ], $overrides));
    }

    public function test_existing_link_returns_linked_user(): void
    {
        $user = User::factory()->create();
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);

        $resolved = app(SocialAuthService::class)->resolveUser($this->profile());

        $this->assertTrue($resolved->is($user));
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_matching_email_auto_links_and_marks_verified(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com', 'email_verified_at' => null]);

        $resolved = app(SocialAuthService::class)
            ->resolveUser($this->profile(['email' => 'existing@example.com']));

        $this->assertTrue($resolved->is($user));
        $this->assertNotNull($resolved->fresh()->email_verified_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id'     => $user->id,
            'provider'    => 'google',
            'provider_id' => 'g-1',
        ]);
    }

    public function test_unknown_email_creates_user_and_applies_invitations(): void
    {
        $band = Bands::factory()->create();
        Invitations::create([
            'band_id'        => $band->id,
            'email'          => 'new@example.com',
            'invite_type_id' => PendingInvitationService::MEMBER_INVITE_TYPE,
            'pending'        => true,
        ]);

        $resolved = app(SocialAuthService::class)->resolveUser($this->profile());

        $this->assertSame('New Person', $resolved->name);
        $this->assertNull($resolved->password);
        $this->assertNotNull($resolved->email_verified_at);
        $this->assertTrue($resolved->fresh()->bandMember->contains('id', $band->id));
    }

    public function test_missing_name_falls_back_to_email_local_part(): void
    {
        $resolved = app(SocialAuthService::class)
            ->resolveUser($this->profile(['provider' => 'apple', 'providerId' => 'a-1', 'email' => 'jane.doe@example.com', 'name' => null]));

        $this->assertSame('jane.doe', $resolved->name);
    }
}
