<?php

namespace Tests\Feature;

use App\Models\BandMembers;
use App\Models\Bands;
use App\Models\Invitations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Public landing page for the invite QR URL (https://tts.band/invite/{key}).
 *
 * Phones with the app installed never reach this route — App Links /
 * Universal Links open the app directly. Browsers land here to get the app
 * or join on the web.
 */
class InviteLandingTest extends TestCase
{
    use RefreshDatabase;

    const MEMBER_INVITE_TYPE = 2;

    private function makeInvitation(?Bands $band = null): Invitations
    {
        $band ??= Bands::factory()->create();

        return Invitations::create([
            'email' => null, // reusable QR invitation
            'band_id' => $band->id,
            'invite_type_id' => self::MEMBER_INVITE_TYPE,
            'pending' => true,
        ]);
    }

    public function test_guest_sees_landing_page_with_band_name_for_valid_key(): void
    {
        $band = Bands::factory()->create(['name' => 'The Test Tones']);
        $invitation = $this->makeInvitation($band);

        $this->get("/invite/{$invitation->key}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/InviteLanding')
                ->where('valid', true)
                ->where('bandName', 'The Test Tones')
                ->where('inviteKey', $invitation->key));
    }

    public function test_landing_page_exposes_store_urls_from_config(): void
    {
        config([
            'services.mobile_app.app_store_url' => 'https://apps.apple.com/app/example',
            'services.mobile_app.play_store_url' => 'https://play.google.com/store/apps/details?id=tts.band',
        ]);
        $invitation = $this->makeInvitation();

        $this->get("/invite/{$invitation->key}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/InviteLanding')
                ->where('appStoreUrl', 'https://apps.apple.com/app/example')
                ->where('playStoreUrl', 'https://play.google.com/store/apps/details?id=tts.band'));
    }

    public function test_unknown_key_renders_invalid_state_without_band_name(): void
    {
        $this->get('/invite/not-a-real-key')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/InviteLanding')
                ->where('valid', false)
                ->where('bandName', null));
    }

    public function test_consumed_invitation_renders_invalid_state(): void
    {
        $invitation = $this->makeInvitation();
        $invitation->pending = false;
        $invitation->save();

        $this->get("/invite/{$invitation->key}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('valid', false));
    }

    public function test_valid_key_is_remembered_in_session_and_login_returns_here(): void
    {
        $invitation = $this->makeInvitation();

        $this->get("/invite/{$invitation->key}")
            ->assertSessionHas('pending_invite_key', $invitation->key)
            ->assertSessionHas('url.intended', route('invite.landing', $invitation->key));
    }

    public function test_invalid_key_is_not_remembered_in_session(): void
    {
        $this->get('/invite/not-a-real-key')
            ->assertSessionMissing('pending_invite_key');
    }

    public function test_onboarding_prefills_join_form_with_remembered_key(): void
    {
        $invitation = $this->makeInvitation();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['pending_invite_key' => $invitation->key])
            ->get(route('onboarding'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/PathSelection')
                ->where('pendingInviteKey', $invitation->key));
    }

    public function test_successful_join_clears_the_remembered_key(): void
    {
        $invitation = $this->makeInvitation();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['pending_invite_key' => $invitation->key])
            ->post(route('onboarding.join'), ['key' => $invitation->key]);

        $response->assertRedirect('/dashboard')
            ->assertSessionMissing('pending_invite_key');

        $this->assertDatabaseHas('band_members', [
            'user_id' => $user->id,
            'band_id' => $invitation->band_id,
        ]);
    }

    public function test_authenticated_user_with_a_band_can_still_join_from_landing_flow(): void
    {
        $existingBand = Bands::factory()->create();
        $user = User::factory()->create();
        BandMembers::create(['user_id' => $user->id, 'band_id' => $existingBand->id]);

        $invitation = $this->makeInvitation();

        $this->actingAs($user)
            ->post(route('onboarding.join'), ['key' => $invitation->key])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('band_members', [
            'user_id' => $user->id,
            'band_id' => $invitation->band_id,
        ]);
    }
}
