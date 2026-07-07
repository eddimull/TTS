<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'api/stripe',
        // Apple's social-login callback arrives cross-site via form_post, so it
        // carries no CSRF token and drops the SameSite=lax session cookie.
        'auth/apple/callback',
        // Broadcasting auth serves both web (session) and mobile (Bearer)
        // clients under auth:sanctum; token clients carry no CSRF token.
        'broadcasting/auth',
    ];
}
