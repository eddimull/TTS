<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Bands;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;

class owner
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
        $user = Auth::user();
        if(!$user->ownsBand($request->band->id))
        {
            return redirect(RouteServiceProvider::HOME)
             ->withErrors('You are not an owner of this band.');
        }

        return $next($request);
    }
}
