<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use App\Models\MediaFile;
use App\Models\GoogleDriveConnection;
use App\Models\GoogleDriveFolder;
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
            // Check for connection_id query parameter (for Google Drive browse)
            if ($request->has('connection_id')) {
                $connection = GoogleDriveConnection::find($request->connection_id);
                if ($connection) {
                    $band_id = $connection->band_id;
                }
            }

            // Check for GoogleDriveConnection route parameter
            if (!$band_id) {
                $connection = $request->route('connection');
                if ($connection instanceof GoogleDriveConnection) {
                    $band_id = $connection->band_id;
                } elseif ($connection) {
                    $driveConnection = GoogleDriveConnection::find($connection);
                    if ($driveConnection) {
                        $band_id = $driveConnection->band_id;
                    }
                }
            }

            // Check for GoogleDriveFolder parameter
            if (!$band_id) {
                $folder = $request->route('folder');
                if ($folder instanceof GoogleDriveFolder) {
                    $band_id = $folder->connection->band_id;
                } elseif ($folder) {
                    $driveFolder = GoogleDriveFolder::with('connection')->find($folder);
                    if ($driveFolder && $driveFolder->connection) {
                        $band_id = $driveFolder->connection->band_id;
                    }
                }
            }

            // Try to get band_id from media model if available
            if (!$band_id) {
                // Check both 'media' and 'id' route parameters (download route uses 'id')
                $media = $request->route('media') ?? $request->route('id');
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
