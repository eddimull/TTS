<?php

namespace App\Http\Controllers;

use App\Models\GoogleDriveConnection;
use App\Models\GoogleDriveFolder;
use App\Services\GoogleDriveOAuthService;
use App\Services\GoogleDriveSyncService;
use App\Jobs\SyncGoogleDriveFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class GoogleDriveController extends Controller
{
    public function __construct(
        protected GoogleDriveOAuthService $oauthService,
        protected GoogleDriveSyncService $syncService
    ) {}

    /**
     * Initiate OAuth flow
     */
    public function connect(Request $request)
    {
        $request->validate(['band_id' => 'required|exists:bands,id']);

        $user = Auth::user();
        $bandId = $request->band_id;

        if (!$user->canWrite('media', $bandId)) {
            abort(403, 'You need write_media permission to connect Google Drive');
        }

        $authUrl = $this->oauthService->getAuthorizationUrl($user->id, $bandId);
        return redirect($authUrl);
    }

    /**
     * Handle OAuth callback
     */
    public function callback(Request $request)
    {
        if (!$request->has('code') || !$request->has('state')) {
            return redirect()->route('media.index')
                ->with('errorMessage', 'OAuth authorization was cancelled or failed.');
        }

        $code = $request->code;
        $stateData = json_decode(base64_decode($request->state), true);

        // Verify state timestamp (prevent replay attacks)
        if (now()->timestamp - $stateData['timestamp'] > 600) {
            return redirect()->route('media.index')
                ->with('errorMessage', 'OAuth flow expired. Please try again.');
        }

        try {
            $tokens = $this->oauthService->handleCallback($code);

            // Check if connection already exists
            $existingConnection = GoogleDriveConnection::where('user_id', $stateData['user_id'])
                ->where('band_id', $stateData['band_id'])
                ->where('google_account_email', $tokens['email'])
                ->withTrashed()
                ->first();

            if ($existingConnection) {
                // Restore if soft deleted
                if ($existingConnection->trashed()) {
                    $existingConnection->restore();
                }

                // Update tokens
                $existingConnection->update([
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? $existingConnection->refresh_token,
                    'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                    'is_active' => true,
                    'sync_status' => 'pending',
                    'last_sync_error' => null,
                ]);

                $connection = $existingConnection;
            } else {
                // Create new connection
                $connection = GoogleDriveConnection::create([
                    'user_id' => $stateData['user_id'],
                    'band_id' => $stateData['band_id'],
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                    'google_account_email' => $tokens['email'],
                    'sync_status' => 'pending',
                ]);
            }

            return redirect()->route('media.index', ['band_id' => $stateData['band_id']])
                ->with('successMessage', 'Google Drive connected! Go to the Google Drive tab to select folders to sync.')
                ->with('drive_connection_id', $connection->id);

        } catch (\Exception $e) {
            \Log::error('Google Drive OAuth callback failed', [
                'error' => $e->getMessage(),
                'state' => $stateData,
            ]);

            return redirect()->route('media.index')
                ->with('errorMessage', 'Failed to connect Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Browse Drive folders for selection
     */
    public function browseFolders(Request $request)
    {
        $request->validate([
            'connection_id' => 'required|exists:google_drive_connections,id',
            'parent_id' => 'nullable|string',
        ]);

        $connection = GoogleDriveConnection::findOrFail($request->connection_id);

        if (!Auth::user()->canRead('media', $connection->band_id)) {
            abort(403);
        }

        try {
            \Log::info('Browsing Google Drive folders', [
                'connection_id' => $connection->id,
                'parent_id' => $request->parent_id,
                'token_expired' => $connection->isTokenExpired(),
            ]);

            $folders = $this->syncService->browseFolders($connection, $request->parent_id);

            \Log::info('Successfully browsed Google Drive folders', [
                'connection_id' => $connection->id,
                'folder_count' => count($folders),
            ]);

            return response()->json(['folders' => $folders]);
        } catch (\Exception $e) {
            \Log::error('Failed to browse Google Drive folders', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add folders to sync
     */
    public function addFolders(Request $request)
    {
        $request->validate([
            'connection_id' => 'required|exists:google_drive_connections,id',
            'folders' => 'required|array|min:1',
            'folders.*.google_folder_id' => 'required|string',
            'folders.*.google_folder_name' => 'required|string',
            'folders.*.local_folder_path' => 'nullable|string',
        ]);

        $connection = GoogleDriveConnection::findOrFail($request->connection_id);

        if (!Auth::user()->canWrite('media', $connection->band_id)) {
            abort(403);
        }

        $addedFolders = [];

        foreach ($request->folders as $folderData) {
            // Check if folder already exists
            $existingFolder = GoogleDriveFolder::where('connection_id', $connection->id)
                ->where('google_folder_id', $folderData['google_folder_id'])
                ->first();

            if ($existingFolder) {
                continue; // Skip if already added
            }

            $folder = GoogleDriveFolder::create([
                'connection_id' => $connection->id,
                'google_folder_id' => $folderData['google_folder_id'],
                'google_folder_name' => $folderData['google_folder_name'],
                'local_folder_path' => $folderData['local_folder_path'] ?? 'Drive/' . $folderData['google_folder_name'],
                'auto_sync' => true,
            ]);

            $addedFolders[] = $folder;

            // Dispatch sync job for this folder
            SyncGoogleDriveFolder::dispatch($folder, 'manual');
        }

        return response()->json([
            'message' => count($addedFolders) . ' folder(s) added. Sync has started in the background.',
            'folders' => $addedFolders,
        ]);
    }

    /**
     * Manually trigger sync for a folder
     */
    public function syncFolder(GoogleDriveFolder $folder)
    {
        $connection = $folder->connection;

        if (!Auth::user()->canWrite('media', $connection->band_id)) {
            abort(403);
        }

        try {
            // Dispatch job for background processing
            SyncGoogleDriveFolder::dispatch($folder, 'manual');

            return redirect()->back()
                ->with('successMessage', 'Sync started in background. Check back in a few minutes.');
        } catch (\Exception $e) {
            \Log::error('Failed to dispatch sync job', [
                'folder_id' => $folder->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('errorMessage', 'Failed to start sync: ' . $e->getMessage());
        }
    }

    /**
     * Remove a folder from sync
     */
    public function removeFolder(GoogleDriveFolder $folder)
    {
        $connection = $folder->connection;

        if (!Auth::user()->canWrite('media', $connection->band_id)) {
            abort(403);
        }

        // Note: This doesn't delete the synced files, just stops syncing this folder
        $folder->delete();

        return redirect()->back()
            ->with('successMessage', 'Folder removed from sync. Previously synced files remain in your library.');
    }

    /**
     * Disconnect Google Drive
     */
    public function disconnect(GoogleDriveConnection $connection)
    {
        \Log::info('Disconnect method called', [
            'connection_id' => $connection->id,
            'band_id' => $connection->band_id,
            'deleted_at_before' => $connection->deleted_at,
        ]);

        if (!Auth::user()->canWrite('media', $connection->band_id)) {
            \Log::warning('Permission denied for disconnect', [
                'connection_id' => $connection->id,
                'user_id' => Auth::id(),
            ]);
            abort(403);
        }

        // Try to revoke token
        try {
            $this->oauthService->revokeToken($connection);
            \Log::info('Token revoked successfully', ['connection_id' => $connection->id]);
        } catch (\Exception $e) {
            \Log::warning('Failed to revoke token during disconnect', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Soft delete connection (preserves synced files)
        \Log::info('Attempting to delete connection', ['connection_id' => $connection->id]);
        $deleted = $connection->delete();
        \Log::info('Delete result', [
            'connection_id' => $connection->id,
            'delete_result' => $deleted,
            'deleted_at_after' => $connection->deleted_at,
        ]);

        return redirect()->back()
            ->with('successMessage', 'Google Drive disconnected. Previously synced files remain in your library.');
    }

    /**
     * Get connection status
     */
    public function status(GoogleDriveConnection $connection)
    {
        if (!Auth::user()->canRead('media', $connection->band_id)) {
            abort(403);
        }

        $connection->load(['folders', 'syncLogs' => function($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json($connection);
    }
}
