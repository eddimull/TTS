<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Auth;

class CanReadSongs
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::user()) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'You must be logged in');
        }

        // Index route resolves band_id from query param; controller handles no-band case
        if ($request->route()->getName() === 'songs.index') {
            return $next($request);
        }

        $band_id = $request->route('song')?->band_id ?? $request->band_id;

        if (!$band_id) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Band not found');
        }

        if (!Auth::user()->canRead('songs', $band_id)) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Permission denied');
        }

        return $next($request);
    }
}
