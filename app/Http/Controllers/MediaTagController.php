<?php

namespace App\Http\Controllers;

use App\Models\MediaTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MediaTagController extends Controller
{
    /**
     * Store a new tag
     */
    public function store(Request $request)
    {
        $request->validate([
            'band_id' => 'required|exists:bands,id',
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/'
        ]);

        $user = Auth::user();

        if (!$user->canWrite('media', $request->band_id)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $tag = MediaTag::create($request->only(['band_id', 'name', 'color']));

        return response()->json($tag);
    }

    /**
     * Update an existing tag
     */
    public function update(Request $request, MediaTag $tag)
    {
        $user = Auth::user();

        if (!$user->canWrite('media', $tag->band_id)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/'
        ]);

        $tag->update($request->only(['name', 'color']));

        return response()->json($tag);
    }

    /**
     * Delete a tag
     */
    public function destroy(MediaTag $tag)
    {
        $user = Auth::user();

        if (!$user->canWrite('media', $tag->band_id)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $tag->delete();

        return response()->json(['message' => 'Tag deleted successfully']);
    }
}
