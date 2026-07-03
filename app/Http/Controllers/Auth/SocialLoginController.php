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
    private const PROVIDERS = ['google', 'apple', 'facebook'];

    public function __construct(private readonly SocialAuthService $socialAuth) {}

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $driver = Socialite::driver($provider);

        // Apple returns via cross-site form_post, which drops the SameSite=lax
        // session cookie — state validation would always fail. Go stateless.
        if ($provider === 'apple') {
            $driver->stateless();
        }

        return $driver->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        try {
            $driver = Socialite::driver($provider);
            $socialiteUser = $provider === 'apple' ? $driver->stateless()->user() : $driver->user();

            $email = $socialiteUser->getEmail();
            if (!$email) {
                return redirect()->route('login')->withErrors([
                    'email' => ucfirst($provider) . ' did not share an email address. Please log in with email instead.',
                ]);
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
