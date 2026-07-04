<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialAuth\SocialAuthService;
use App\Services\SocialAuth\SocialProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    public function __construct(private readonly SocialAuthService $socialAuth) {}

    /**
     * Providers enabled for the web redirect/callback flow. Facebook is gated
     * behind FACEBOOK_LOGIN_ENABLED until Meta business verification is
     * complete (see config/services.php).
     */
    private function enabledProviders(): array
    {
        $providers = ['google', 'apple'];

        if (config('services.facebook.enabled')) {
            $providers[] = 'facebook';
        }

        return $providers;
    }

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, $this->enabledProviders(), true), 404);

        $driver = Socialite::driver($provider);

        // Apple returns via cross-site form_post, which drops the SameSite=lax
        // session cookie — state validation would always fail. Go stateless.
        //
        // SECURITY (accepted risk, not fixed here): Apple's form_post response
        // mode pairs with our SameSite=lax session cookie in a way that forces
        // stateless() — Socialite's `state` param can never be validated for
        // this provider. That means the Apple callback is vulnerable to
        // login-CSRF: an attacker can capture their OWN valid callback POST
        // body and get a victim's browser to auto-submit it (e.g. via an
        // auto-submitting cross-site form), silently logging the victim into
        // the attacker's account. This is accepted for launch. Mitigation
        // (issuing a short-lived SameSite=None state cookie so Apple's
        // form_post can still read it) is tracked as a follow-up, not
        // implemented now.
        if ($provider === 'apple') {
            $driver->stateless();
        }

        return $driver->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, $this->enabledProviders(), true), 404);

        try {
            $driver = Socialite::driver($provider);
            $socialiteUser = $provider === 'apple' ? $driver->stateless()->user() : $driver->user();

            $email = $socialiteUser->getEmail();
            if (!$email) {
                return redirect()->route('login')->withErrors([
                    'email' => ucfirst($provider) . ' did not share an email address. Please log in with email instead.',
                ]);
            }

            if (in_array($provider, ['google', 'apple'], true)) {
                // Google and Apple both relay an email_verified claim into the raw
                // user payload Socialite exposes as $socialiteUser->user. Mirror the
                // fail-closed check in AbstractIdTokenVerifier (see commit 1aa4b00f):
                // a present-but-falsy OR missing claim is treated as unverified, so a
                // spoofed/unverified email can never auto-link to an existing account.
                //
                // Google always sends this claim, so its absence is suspicious.
                //
                // Apple: SocialiteProviders\Apple\Provider::mapUserToObject() calls
                // setRaw($user) with the full decoded id_token payload (see
                // vendor/socialiteproviders/apple/Provider.php), and Apple's identity
                // token spec documents email_verified as a standard claim Apple sends
                // on every id_token. So the driver DOES relay it in normal operation —
                // we fail closed on a missing claim for Apple too, same as Google.
                $emailVerified = $socialiteUser->user['email_verified'] ?? null;
                if ($emailVerified !== true && $emailVerified !== 'true') {
                    return redirect()->route('login')->withErrors([
                        'email' => ucfirst($provider) . ' reported your email address as unverified.',
                    ]);
                }
            }

            $user = $this->socialAuth->resolveUser(new SocialProfile(
                provider: $provider,
                providerId: (string) $socialiteUser->getId(),
                email: $email,
                name: $socialiteUser->getName(),
                avatarUrl: $socialiteUser->getAvatar(),
            ));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors([
                'email' => 'Social sign-in failed. Please try again.',
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
