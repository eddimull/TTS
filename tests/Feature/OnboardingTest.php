<?php

namespace Tests\Feature;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Models\Invitations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    const OWNER_INVITE_TYPE = 1;
    const MEMBER_INVITE_TYPE = 2;

    public function test_new_user_without_a_band_is_redirected_to_onboarding(): void
    {
        $response = $this->post('/register', [
            'name' => 'No Band',
            'email' => 'noband@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('onboarding'));
    }

    public function test_new_user_with_a_pending_invitation_skips_onboarding(): void
    {
        $band = Bands::factory()->create();

        Invitations::create([
            'email' => 'invited@example.com',
            'band_id' => $band->id,
            'invite_type_id' => self::MEMBER_INVITE_TYPE,
            'pending' => true,
        ]);

        $response = $this->post('/register', [
            'name' => 'Invited User',
            'email' => 'invited@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard');
    }

    public function test_onboarding_page_renders_for_a_band_less_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertOk();
    }

    public function test_onboarding_page_redirects_users_who_already_have_a_band(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::factory()->create(['user_id' => $user->id, 'band_id' => $band->id]);

        $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertRedirect('/dashboard');
    }

    public function test_user_can_go_solo_and_gets_a_personal_band(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('onboarding.solo'));

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('bands', [
            'name' => "{$user->name}'s Band",
            'is_personal' => true,
        ]);
        $this->assertTrue($user->fresh()->ownsBand(Bands::where('is_personal', true)->first()->id));
    }

    public function test_go_solo_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.solo'));
        $this->actingAs($user)->post(route('onboarding.solo'))->assertRedirect('/dashboard');

        $this->assertEquals(
            1,
            Bands::where('is_personal', true)
                ->whereHas('owners', fn ($q) => $q->where('user_id', $user->id))
                ->count()
        );
    }

    public function test_unique_site_name_uses_fallback_base_for_suffixed_candidates(): void
    {
        // When the slug base is empty, uniqueSiteName() falls back to 'band'.
        // If 'band' is taken, the next candidate must build on the fallback
        // ('band-1') rather than the empty base ('-1'). Exercised directly
        // because the go-solo name "<name>'s Band" rarely slugs to empty.
        Bands::factory()->create(['site_name' => 'band']);

        $controller = new OnboardingController();
        $method = new \ReflectionMethod($controller, 'uniqueSiteName');
        $method->setAccessible(true);

        $this->assertEquals('band-1', $method->invoke($controller, ''));
    }

    public function test_site_name_has_a_unique_constraint(): void
    {
        Bands::factory()->create(['site_name' => 'taken-name']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Bands::factory()->create(['site_name' => 'taken-name']);
    }

    public function test_solo_is_resilient_to_a_pre_existing_site_name_collision(): void
    {
        // A band already squats the exact slug go-solo would generate; the
        // generator must route around it rather than blow up on the unique index.
        $user = User::factory()->create(['name' => 'Collision Carl']);
        Bands::factory()->create(['site_name' => 'collision-carls-band']);

        $this->actingAs($user)->post(route('onboarding.solo'))->assertRedirect('/dashboard');

        $this->assertDatabaseHas('bands', [
            'name' => "Collision Carl's Band",
            'site_name' => 'collision-carls-band-1',
            'is_personal' => true,
        ]);
    }

    public function test_user_can_join_a_band_as_member_with_an_invite_code(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();

        $invitation = Invitations::create([
            'email' => $user->email,
            'band_id' => $band->id,
            'invite_type_id' => self::MEMBER_INVITE_TYPE,
            'pending' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('onboarding.join'), ['key' => $invitation->key]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('band_members', [
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);
        // Email-addressed invitations are consumed.
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'pending' => false,
        ]);
    }

    public function test_user_can_join_a_band_as_owner_with_an_invite_code(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();

        $invitation = Invitations::create([
            'email' => $user->email,
            'band_id' => $band->id,
            'invite_type_id' => self::OWNER_INVITE_TYPE,
            'pending' => true,
        ]);

        $this->actingAs($user)
            ->post(route('onboarding.join'), ['key' => $invitation->key])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('band_owners', [
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);
        $this->assertTrue($user->fresh()->ownsBand($band->id));
    }

    public function test_reusable_qr_invitation_stays_pending_after_join(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();

        // Reusable QR invitations have a null email and must stay pending.
        $invitation = Invitations::create([
            'email' => null,
            'band_id' => $band->id,
            'invite_type_id' => self::MEMBER_INVITE_TYPE,
            'pending' => true,
        ]);

        $this->actingAs($user)
            ->post(route('onboarding.join'), ['key' => $invitation->key])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('band_members', [
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'pending' => true,
        ]);
    }

    public function test_join_with_invalid_code_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.join'), ['key' => 'not-a-real-code'])
            ->assertSessionHasErrors('key');

        $this->assertDatabaseCount('band_members', 0);
    }

    public function test_creating_first_band_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();

        // The onboarding "Create a Band" path uses the existing bands.store
        // route; a user's first band should land on the dashboard.
        $this->actingAs($user)
            ->post('/bands', ['name' => 'My First Band', 'site_name' => 'my-first-band'])
            ->assertRedirect('/dashboard');
    }

    public function test_creating_a_subsequent_band_redirects_to_bands_index(): void
    {
        $user = User::factory()->create();
        $existing = Bands::factory()->create();
        BandOwners::factory()->create(['user_id' => $user->id, 'band_id' => $existing->id]);

        $this->actingAs($user)
            ->post('/bands', ['name' => 'Second Band', 'site_name' => 'second-band'])
            ->assertRedirect(route('bands'));
    }
}
