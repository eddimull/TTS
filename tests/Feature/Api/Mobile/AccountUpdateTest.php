<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_editable_fields(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'emailNotifications' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->patchJson('/api/mobile/account', [
            'name'                => 'New Name',
            'email'               => 'new@example.com',
            'city'                => 'New Orleans',
            'zip'                 => '70112',
            'email_notifications' => false,
        ]);

        $response->assertOk();
        $this->assertSame('New Name', $response->json('account.name'));

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame('New Orleans', $user->City);
        $this->assertSame('70112', $user->Zip);
        $this->assertFalse((bool) $user->emailNotifications);
    }

    public function test_accepts_numeric_state_and_country_ids(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Client sends numeric IDs (as the lookup lists expose them) — must not 422.
        $response = $this->withToken($token)->patchJson('/api/mobile/account', [
            'name'                => $user->name,
            'email'               => $user->email,
            'state_id'            => 12,
            'country_id'          => 1,
            'email_notifications' => true,
        ]);

        $response->assertOk();
        // Returned as ints for type consistency with the lookup lists.
        $this->assertSame(12, $response->json('account.state_id'));
        $this->assertSame(1, $response->json('account.country_id'));

        $user->refresh();
        $this->assertSame('12', $user->StateID); // stored as varchar
        $this->assertSame('1', $user->CountryID);
    }

    public function test_rejects_non_scalar_state_and_country_ids(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // An array would otherwise cast to the string "Array" and corrupt data.
        $this->withToken($token)->patchJson('/api/mobile/account', [
            'name'                => $user->name,
            'email'               => $user->email,
            'state_id'            => ['evil'],
            'email_notifications' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['state_id']);
    }

    public function test_password_only_changes_when_provided(): void
    {
        $user = User::factory()->create(['password' => Hash::make('original-pass')]);
        $token = $user->createToken('test')->plainTextToken;

        // No password field — existing hash preserved.
        $this->withToken($token)->patchJson('/api/mobile/account', [
            'name'                => $user->name,
            'email'               => $user->email,
            'email_notifications' => true,
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('original-pass', $user->password));

        // With a confirmed password — hash updates.
        $this->withToken($token)->patchJson('/api/mobile/account', [
            'name'                  => $user->name,
            'email'                 => $user->email,
            'password'              => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
            'email_notifications'   => true,
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-pass', $user->password));
    }

    public function test_email_must_be_unique_to_other_users(): void
    {
        // Reserve the email on another account so the uniqueness rule fires.
        User::factory()->create(['email' => 'taken@example.com']);
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->patchJson('/api/mobile/account', [
            'name'                => $user->name,
            'email'               => 'taken@example.com',
            'email_notifications' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_keeping_own_email_is_allowed(): void
    {
        $user  = User::factory()->create(['email' => 'mine@example.com']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->patchJson('/api/mobile/account', [
            'name'                => 'Renamed',
            'email'               => 'mine@example.com',
            'email_notifications' => true,
        ])->assertOk();
    }

    public function test_requires_authentication(): void
    {
        $this->patchJson('/api/mobile/account', [])->assertUnauthorized();
    }
}
