<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use App\Models\Charts;
use Auth;


class CanWriteCharts
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
        $band_id = 0;
        if(!$request->band_id)
        {
            $chart = $request->route('chart');
            $band_id = $chart->band_id;
        }
        else
        {
            $band_id = $request->band_id;
        }


        if(!Auth::user()->canWriteCharts($band_id))
        {
            return redirect(RouteServiceProvider::HOME)
            ->withErrors('You do not have permission to create charts');
        }
        return $next($request);
    }
}
