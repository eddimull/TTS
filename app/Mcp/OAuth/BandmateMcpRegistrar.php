<?php

namespace App\Mcp\OAuth;

use App\Mcp\Support\McpScopes;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;

/**
 * laravel/mcp's registrar, with discovery metadata that advertises our own
 * scopes instead of the generic `mcp:use`, and a throttle on the open dynamic
 * client registration endpoint. Bound over the package's Registrar in
 * McpOAuthServiceProvider so `Mcp::oauthRoutes()` uses it.
 */
class BandmateMcpRegistrar extends Registrar
{
    public function oauthRoutes(string $oauthPrefix = 'oauth'): void
    {
        parent::oauthRoutes($oauthPrefix);

        // Registration is unauthenticated by design (RFC 7591 / MCP spec);
        // cap how fast one IP can mint clients.
        foreach (Router::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if ($route->uri() === trim($oauthPrefix, '/') . '/register' && in_array('POST', $route->methods(), true)) {
                $route->middleware('throttle:10,1')->name('mcp.oauth.register');
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public static function ensureMcpScope(): array
    {
        if (! class_exists(Passport::class)) {
            return [];
        }

        $scopes = array_merge(
            Passport::$scopes ?? [],
            McpScopes::descriptions(),
            [McpScopes::UMBRELLA => 'Full access: everything listed above that your band permissions allow'],
        );

        Passport::tokensCan($scopes);

        return $scopes;
    }

    protected static function authorizationServerMetadata(string $oauthPrefix): array
    {
        return array_merge(parent::authorizationServerMetadata($oauthPrefix), [
            'scopes_supported' => McpScopes::ids(),
            'token_endpoint_auth_methods_supported' => ['none'],
        ]);
    }

    protected static function protectedResourceMetadata(string $path): array
    {
        return array_merge(parent::protectedResourceMetadata($path), [
            'scopes_supported' => McpScopes::ids(),
            'bearer_methods_supported' => ['header'],
            'resource_name' => config('app.name', 'TTS Bandmate'),
        ]);
    }
}
