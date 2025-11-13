<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        // Check if user is trying to access the wrong login portal
        // If a contact is logged in and tries to access band member routes
        if (Auth::guard('contact')->check() && !$request->is('portal/*')) {
            return redirect()->route('portal.dashboard');
        }

        // If a band member is logged in and tries to access contact portal routes
        if (Auth::guard('web')->check() && $request->is('portal/*')) {
            return redirect()->route('dashboard');
        }

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Redirect based on guard type
                if ($guard === 'contact') {
                    return redirect()->route('portal.dashboard');
                }

                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
