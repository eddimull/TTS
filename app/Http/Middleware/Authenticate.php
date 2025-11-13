<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            // Check if this is a contact portal route
            if ($request->is('portal/*') || $request->is('portal')) {
                // If a band member is authenticated but accessing portal routes,
                // redirect them to their dashboard
                if (auth('web')->check()) {
                    return route('dashboard');
                }
                return route('portal.login');
            }

            // If a contact is authenticated but accessing band member routes,
            // redirect them to portal dashboard
            if (auth('contact')->check()) {
                return route('portal.dashboard');
            }

            return route('login');
        }
    }
}
