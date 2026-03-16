<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Auth;

class CanWriteSongs
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::user()) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'You must be logged in');
        }

        // For store, band_id comes from the request body
        // For update/destroy, derive from the song model
        $band_id = $request->route('song')?->band_id
            ?? $request->input('band_id');

        if (!$band_id) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Band not found');
        }

        if (!Auth::user()->canWrite('songs', $band_id)) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Permission denied');
        }

        return $next($request);
    }
}
