<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Auth;

class CanWriteMedia
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

        // Try to get band_id from request or route parameter
        if (!$request->band_id) {
            $media = $request->route('media');
            if ($media instanceof MediaFile) {
                $band_id = $media->band_id;
            }
        } else {
            $band_id = $request->band_id;
        }

        if (!$band_id) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Band not found');
        }

        if (!Auth::user()->canWrite('media', $band_id)) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Permission denied');
        }

        return $next($request);
    }
}
