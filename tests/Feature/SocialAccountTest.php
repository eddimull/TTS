<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_account_links_to_user_and_enforces_unique_provider_id(): void
    {
        $user = User::factory()->create();

        $account = SocialAccount::create([
            'user_id'     => $user->id,
            'provider'    => 'google',
            'provider_id' => 'g-123',
            'avatar_url'  => 'https://example.com/a.png',
        ]);

        $this->assertTrue($account->user->is($user));
        $this->assertTrue($user->socialAccounts()->first()->is($account));

        $this->expectException(QueryException::class);
        SocialAccount::create([
            'user_id'     => $user->id,
            'provider'    => 'google',
            'provider_id' => 'g-123',
        ]);
    }

    public function test_user_can_be_created_without_password(): void
    {
        $user = User::create([
            'name'     => 'Social Only',
            'email'    => 'social-only@example.com',
            'password' => null,
        ]);

        $this->assertNull($user->fresh()->password);
    }
}
