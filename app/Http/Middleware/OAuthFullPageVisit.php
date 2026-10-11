<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport routes (prefix /oauth) may answer with a redirect to the OAuth
 * client's callback on another origin (e.g. claude.ai). When the browser
 * arrives via an Inertia XHR — typically the login form's
 * redirect()->intended() back to /oauth/authorize — that cross-origin redirect
 * would be blocked, so turn Inertia visits into a full page load instead.
 */
class OAuthFullPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        return $next($request);
    }
}
