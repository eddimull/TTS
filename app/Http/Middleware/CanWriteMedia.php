<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use App\Models\MediaFile;
use App\Models\GoogleDriveConnection;
use App\Models\GoogleDriveFolder;
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
            // Check for connection_id in request body (for addFolders endpoint)
            if ($request->has('connection_id')) {
                $driveConnection = GoogleDriveConnection::find($request->connection_id);
                if ($driveConnection) {
                    $band_id = $driveConnection->band_id;
                }
            }

            // Check for GoogleDriveConnection route parameter
            if (!$band_id) {
                $connection = $request->route('connection');
                if ($connection instanceof GoogleDriveConnection) {
                    $band_id = $connection->band_id;
                } elseif ($connection) {
                    // If connection is just an ID, load the model
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
                    // If folder is just an ID, load the model with connection
                    $driveFolder = GoogleDriveFolder::with('connection')->find($folder);
                    if ($driveFolder && $driveFolder->connection) {
                        $band_id = $driveFolder->connection->band_id;
                    }
                }
            }

            // Check both 'media' and 'id' route parameters
            if (!$band_id) {
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
