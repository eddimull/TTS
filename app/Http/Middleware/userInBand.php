<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;

class userInBand
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if(!$request->user->isPartOfBand($request->band->id))
        {
            return redirect(RouteServiceProvider::HOME)
            ->withErrors('User is not a part of your band');
        }
        return $next($request);
    }
}
