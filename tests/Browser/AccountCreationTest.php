<?php

namespace Tests\Browser;

use App\Models\Bands;
use App\Models\Invitations;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AccountCreationTest extends DuskTestCase
{
    use DatabaseMigrations;

    const MEMBER_INVITE_TYPE = 2;

    /**
     * Skip the parent's teardown migrate:rollback, which fails on this
     * codebase's irreversible migrations. The next test's migrate:fresh
     * resets state cleanly. (Mirrors EventTest.)
     *
     * We do restore any error/exception handlers the framework left
     * registered during the browser request, so PHPUnit doesn't flag the
     * test as risky.
     */
    protected function tearDown(): void
    {
        // The framework leaves one error and one exception handler registered
        // during the browser request; restore them so PHPUnit doesn't flag the
        // test as risky. (Restoring exactly once — over-draining removes
        // PHPUnit's own handlers and trips a different risky check.)
        restore_error_handler();
        restore_exception_handler();
    }

    public function test_login_page_links_to_registration(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitForText('Band Member Login', 10)
                ->assertSee("Don't have an account?")
                ->click('@login-register-link')
                ->waitForLocation('/register', 10)
                ->assertPathIs('/register');
        });
    }

    public function test_new_user_can_register_and_reaches_onboarding(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->waitFor('#name', 10)
                ->type('#name', 'Dusk Newcomer')
                ->type('#email', 'dusk-newcomer@test.local')
                ->type('#password', 'password1234')
                ->type('#password_confirmation', 'password1234')
                ->click('@register-submit')
                ->waitForLocation('/onboarding', 15)
                ->waitForText('How would you like to use Bandmate?', 10)
                ->assertSee('Create a Band')
                ->assertSee('Join a Band')
                ->assertSee('Go Solo');
        });

        $this->assertDatabaseHas('users', ['email' => 'dusk-newcomer@test.local']);
    }

    public function test_user_can_go_solo_from_onboarding(): void
    {
        $user = User::factory()->create([
            'name' => 'Solo Sam',
            'email' => 'solo-sam@test.local',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/onboarding')
                ->waitForText('How would you like to use Bandmate?', 10)
                ->click('@onboarding-solo')
                ->waitForLocation('/dashboard', 15);
        });

        $this->assertDatabaseHas('bands', [
            'name' => "Solo Sam's Band",
            'is_personal' => true,
        ]);
    }

    public function test_user_can_join_a_band_with_an_invite_code_from_onboarding(): void
    {
        $user = User::factory()->create([
            'name' => 'Joiner Jane',
            'email' => 'joiner-jane@test.local',
        ]);
        $band = Bands::factory()->create(['name' => 'The Invitees']);

        $invitation = Invitations::create([
            'email' => $user->email,
            'band_id' => $band->id,
            'invite_type_id' => self::MEMBER_INVITE_TYPE,
            'pending' => true,
        ]);

        $this->browse(function (Browser $browser) use ($user, $invitation) {
            $browser->loginAs($user)
                ->visit('/onboarding')
                ->waitForText('How would you like to use Bandmate?', 10)
                ->type('@onboarding-join-code', $invitation->key)
                ->click('@onboarding-join-submit')
                ->waitForLocation('/dashboard', 15);
        });

        $this->assertDatabaseHas('band_members', [
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);
    }

    public function test_join_with_invalid_code_shows_an_error(): void
    {
        $user = User::factory()->create(['email' => 'bad-code@test.local']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/onboarding')
                ->waitForText('How would you like to use Bandmate?', 10)
                ->type('@onboarding-join-code', 'totally-invalid-code')
                ->click('@onboarding-join-submit')
                ->waitForText('Invalid or expired invite code.', 10)
                ->assertPathIs('/onboarding');
        });
    }

    public function test_create_a_band_card_navigates_to_band_creation(): void
    {
        $user = User::factory()->create(['email' => 'creator@test.local']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/onboarding')
                ->waitForText('How would you like to use Bandmate?', 10)
                ->click('@onboarding-create')
                ->waitForLocation('/bands/create', 15)
                ->waitForText('Create Your Band', 10)
                ->assertSee('Create Your Band');
        });
    }

    public function test_onboarding_redirects_users_who_already_have_a_band(): void
    {
        $user = User::factory()->create(['email' => 'has-band@test.local']);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/onboarding')
                ->waitForLocation('/dashboard', 15)
                ->assertPathIs('/dashboard');
        });
    }
}
