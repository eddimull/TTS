<?php

namespace App\Providers;

use App\Mcp\OAuth\BandmateMcpRegistrar;
use App\Mcp\OAuth\RegisterClientController;
use App\Mcp\Support\McpScopes;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Carbon\CarbonInterval;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;

/**
 * OAuth 2.1 (Laravel Passport) for the remote MCP server at /mcp.
 *
 * Passport is used only by the `mcp` guard; Sanctum (mobile) and the band
 * developer-API tokens are untouched. Routes live in routes/ai.php.
 */
class McpOAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registrar::class, fn () => new BandmateMcpRegistrar);
        $this->app->bind(OAuthRegisterController::class, RegisterClientController::class);

        // Only the authorization-code (+PKCE) and refresh grants are used.
        // Must be set before PassportServiceProvider boots and registers routes.
        Passport::$deviceCodeGrantEnabled = false;
    }

    public function boot(): void
    {
        // See the `mcp_users` provider in config/auth.php.
        Auth::provider('mcp-users', fn ($app) => new EloquentUserProvider($app['hash'], User::class));

        BandmateMcpRegistrar::ensureMcpScope();

        // A client that requests no scope (claude.ai may omit it) is shown —
        // and on approval granted — every scope. Band permissions still apply.
        Passport::defaultScopes(McpScopes::ids());

        // Short-lived access tokens; clients refresh silently. Refresh tokens
        // rotate on use, so an active connector never expires.
        Passport::tokensExpireIn(CarbonInterval::hours(8));
        Passport::refreshTokensExpireIn(CarbonInterval::days(90));

        Passport::authorizationView(fn (array $parameters) => Inertia::render('OAuth/Authorize', [
            'client' => [
                'id' => (string) $parameters['client']->getKey(),
                'name' => $parameters['client']->name,
            ],
            'user' => [
                'name' => $parameters['user']->name,
                'email' => $parameters['user']->email,
            ],
            'scopes' => collect($parameters['scopes'])
                ->map(fn (Scope $scope) => ['id' => $scope->id, 'description' => $scope->description])
                ->values(),
            'authToken' => $parameters['authToken'],
            'csrfToken' => csrf_token(),
            'approveUrl' => route('passport.authorizations.approve'),
            'denyUrl' => route('passport.authorizations.deny'),
        ]));
    }
}
