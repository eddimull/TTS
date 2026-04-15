<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates via web session OR Sanctum Bearer token.
 * Used on /media/thumbnail and /media/serve so the mobile app
 * can load images using its Bearer token while the web app
 * continues to use its session cookie.
 */
class AuthenticateWebOrToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Already authenticated via session
        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        // Try Sanctum Bearer token
        if (Auth::guard('sanctum')->check()) {
            Auth::shouldUse('sanctum');
            return $next($request);
        }

        // Neither — return 401 JSON for API clients, redirect for browsers
        if ($request->expectsJson() || $request->bearerToken()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->route('login');
    }
}
