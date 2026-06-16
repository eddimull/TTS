<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_full_profile_and_lookup_lists(): void
    {
        $user = User::factory()->create([
            'name'               => 'Jane Player',
            'City'               => 'Baton Rouge',
            'emailNotifications' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/account');

        $response->assertOk()
            ->assertJsonStructure([
                'account' => [
                    'id', 'name', 'email', 'address1', 'address2',
                    'city', 'state_id', 'country_id', 'zip', 'email_notifications',
                ],
                'states',
                'countries',
            ]);

        $this->assertSame('Jane Player', $response->json('account.name'));
        $this->assertSame('Baton Rouge', $response->json('account.city'));
        $this->assertTrue($response->json('account.email_notifications'));
        $this->assertArrayNotHasKey('password', $response->json('account'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/mobile/account')->assertUnauthorized();
    }
}
