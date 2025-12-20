<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use App\Models\Charts;
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
        if (!Auth::user()) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'You must be logged in');
        }

        $band_id = 0;

        // Try to get band_id from request or route parameter
        if ($request->band_id) {
            $band_id = $request->band_id;
        } else {
            // Try to get band_id from chart model if available
            $chart = $request->route('chart');
            if ($chart instanceof Charts) {
                $band_id = $chart->band_id;
            } elseif ($chart) {
                // If chart is just an ID, load the model
                $chartModel = Charts::find($chart);
                if ($chartModel) {
                    $band_id = $chartModel->band_id;
                }
            }
        }

        // If no band_id found and we're on index route, allow access
        // The controller will handle filtering by bands the user has access to
        if (!$band_id && $request->route()->getName() === 'charts') {
            return $next($request);
        }

        if (!$band_id) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Band not found');
        }

        if (!Auth::user()->canRead('charts', $band_id)) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Permission denied');
        }

        return $next($request);
    }
}
