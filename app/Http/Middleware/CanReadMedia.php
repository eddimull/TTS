<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Auth;

class CanReadMedia
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
            // Try to get band_id from media model if available
            $media = $request->route('media');
            if ($media instanceof MediaFile) {
                $band_id = $media->band_id;
            } elseif ($media) {
                // If media is just an ID, load the model
                $mediaFile = MediaFile::find($media);
                if ($mediaFile) {
                    $band_id = $mediaFile->band_id;
                }
            }
        }

        // If no band_id found and we're on index route, allow access
        // The controller will handle filtering by bands the user has access to
        if (!$band_id && $request->route()->getName() === 'media.index') {
            return $next($request);
        }

        if (!$band_id) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Band not found');
        }

        if (!Auth::user()->canRead('media', $band_id)) {
            return redirect(RouteServiceProvider::HOME)
                ->with('errorMessage', 'Permission denied');
        }

        return $next($request);
    }
}
