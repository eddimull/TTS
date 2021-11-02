<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use Auth;

class CanReadCharts
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
        
        if(!Auth::user())
        {
            return redirect(RouteServiceProvider::HOME)
            ->withErrors('You do not have permission to read charts');
        }
        return $next($request);
    }
}
