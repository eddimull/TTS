<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;

class userInBand
{
    public function handle(Request $request, Closure $next)
    {
        /**
         * Handle an incoming request.
         *
         * @param  \Illuminate\Http\Request  $request
         * @param  \Closure  $next
         * @return mixed
         */
        // Get the authenticated user using the method call
        $user = $request->user();

        // Assuming you have route model binding for 'band'
        $band = $request->route('band');

        if (!$user || !$band || (!$user->isPartOfBand($band->id) && !$user->ownsBand($band->id))) {
            return redirect(RouteServiceProvider::HOME)
                ->withErrors('User is not a part requested band.');
        }

        return $next($request);
    }
}
