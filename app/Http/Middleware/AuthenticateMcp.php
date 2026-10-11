<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the remote MCP endpoint with a Passport (OAuth 2.1) bearer
 * token on the `mcp` guard.
 *
 * Unlike `auth:mcp`, this never redirects to the login page: a missing,
 * expired or revoked token is always a 401, which laravel/mcp's
 * AddWwwAuthenticateHeader turns into the `WWW-Authenticate: Bearer
 * resource_metadata=...` challenge that starts the client's OAuth flow.
 */
class AuthenticateMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('mcp')->check()) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'A valid OAuth access token is required. Connect TTS Bandmate to authorize.',
            ], 401);
        }

        Auth::shouldUse('mcp');

        return $next($request);
    }
}
