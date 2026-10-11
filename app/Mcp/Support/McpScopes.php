<?php

namespace App\Mcp\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * OAuth scopes for the remote MCP server (Laravel Passport `tokensCan`).
 *
 * A scope limits what the *connection* may do; it never widens what the user
 * may do. Every tool also checks the user's own permission in the band.
 */
final class McpScopes
{
    public const BOOKINGS_READ = 'bookings:read';
    public const BOOKINGS_WRITE = 'bookings:write';
    public const EVENTS_READ = 'events:read';
    public const PRICING_READ = 'pricing:read';

    /**
     * laravel/mcp registers this generic scope itself. We don't advertise it,
     * but a client that asks for it gets the consent screen's "full access"
     * wording and it is honoured as every scope above.
     */
    public const UMBRELLA = 'mcp:use';

    /**
     * Scope id => consent-screen description.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            self::BOOKINGS_READ => 'View your bands\' bookings and booked dates',
            self::BOOKINGS_WRITE => 'Create, update and delete bookings',
            self::EVENTS_READ => 'View your bands\' events',
            self::PRICING_READ => 'Get price estimates (band owners only)',
        ];
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::descriptions());
    }

    /**
     * Whether the current access token carries the scope.
     *
     * Users are authenticated by Passport's guard, which attaches the access
     * token via withAccessToken(); tokenCan() reads it.
     */
    public static function granted(Authenticatable $user, string $scope): bool
    {
        if (! method_exists($user, 'tokenCan')) {
            return false;
        }

        return $user->tokenCan($scope) || $user->tokenCan(self::UMBRELLA);
    }
}
