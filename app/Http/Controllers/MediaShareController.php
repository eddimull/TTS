<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Models\MediaShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MediaShareController extends Controller
{
    /**
     * Create a new share link for a media file
     */
    public function create(Request $request, MediaFile $media)
    {
        $user = Auth::user();

        if (!$user->canWrite('media', $media->band_id)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $request->validate([
            'expires_at' => 'nullable|date|after:now',
            'download_limit' => 'nullable|integer|min:1',
            'password' => 'nullable|string|min:4'
        ]);

        $share = MediaShare::create([
            'media_file_id' => $media->id,
            'created_by' => $user->id,
            'expires_at' => $request->expires_at,
            'download_limit' => $request->download_limit,
            'password_hash' => $request->password ? bcrypt($request->password) : null
        ]);

        return response()->json([
            'share' => $share,
            'url' => route('media.share.access', $share->token)
        ]);
    }

    /**
     * Access a shared media file via public link
     */
    public function access($token, Request $request)
    {
        $share = MediaShare::where('token', $token)->firstOrFail();
        $media = $share->mediaFile;

        // Check if password is required and not yet verified
        if ($share->password_hash && !$request->session()->has("share_access_{$token}")) {
            if (!$request->password) {
                // Return a simple password form
                return response()->view('media.share-password', [
                    'token' => $token,
                    'filename' => $media->filename
                ]);
            }

            // Verify password
            if (!$share->canAccess($request->password)) {
                return response()->view('media.share-password', [
                    'token' => $token,
                    'filename' => $media->filename,
                    'error' => 'Invalid password'
                ]);
            }

            // Store password verification in session
            $request->session()->put("share_access_{$token}", true);
        }

        // Check access without password verification (for already verified or non-password-protected shares)
        if (!$share->canAccess()) {
            abort(403, 'This share link is no longer valid');
        }

        // Increment download count
        $share->increment('download_count');

        // Serve the file
        try {
            return Storage::disk($media->disk)->download(
                $media->stored_filename,
                $media->filename
            );
        } catch (\Exception $e) {
            \Log::error('Failed to serve shared media file', [
                'share_id' => $share->id,
                'media_id' => $media->id,
                'error' => $e->getMessage()
            ]);
            abort(404, 'File not found');
        }
    }

    /**
     * Delete a share link
     */
    public function destroy(MediaShare $share)
    {
        $user = Auth::user();

        if (!$user->canWrite('media', $share->mediaFile->band_id)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $share->delete();

        return response()->json(['message' => 'Share link deleted successfully']);
    }
}
