<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSongRequest;
use App\Http\Requests\UpdateSongRequest;
use App\Models\Bands;
use App\Models\Song;
use App\Services\GetSongBpmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response as InertiaResponse;

class SongsController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        $bands = $user->allBands();
        $currentBandId = $request->get('band_id', $bands->first()?->id);

        if (!$currentBandId) {
            return inertia('Songs/Index', [
                'band' => null,
                'songs' => [],
                'rosterMembers' => [],
                'genres' => Song::GENRES,
                'availableBands' => [],
                'canWrite' => false,
            ]);
        }

        $band = Bands::findOrFail($currentBandId);

        if (!$band->everyone()->contains('user_id', $user->id) && !$user->canRead('songs', $band->id)) {
            abort(403, 'Unauthorized');
        }

        $songs = $band->songs()
            ->with(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')])
            ->get();

        $rosterMembers = $band->rosters()
            ->with(['members' => fn($q) => $q->where('is_active', true)->with('user')])
            ->get()
            ->pluck('members')
            ->flatten()
            ->unique('id')
            ->values()
            ->map(fn($m) => [
                'id' => $m->id,
                'display_name' => $m->display_name,
            ]);

        return inertia('Songs/Index', [
            'band' => $band,
            'songs' => $songs,
            'rosterMembers' => $rosterMembers,
            'genres' => Song::GENRES,
            'availableBands' => $bands->map(fn($b) => ['id' => $b->id, 'name' => $b->name]),
            'canWrite' => $user->canWrite('songs', $band->id),
        ]);
    }

    public function store(StoreSongRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $band = Bands::findOrFail($validated['band_id']);

        if (!Auth::user()->canWrite('songs', $band->id)) {
            abort(403, 'Permission denied');
        }

        $song = $band->songs()->create($validated);
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')]);

        return response()->json($song, 201);
    }

    public function update(UpdateSongRequest $request, Song $song): JsonResponse
    {
        if (!Auth::user()->canWrite('songs', $song->band_id)) {
            abort(403, 'Permission denied');
        }

        $song->update($request->validated());
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')]);

        return response()->json($song);
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'title'  => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
        ]);

        $result = (new GetSongBpmService())->lookup(
            $request->input('title'),
            $request->input('artist')
        );

        return response()->json($result);
    }

    public function destroy(Song $song): JsonResponse
    {
        if (!Auth::user()->ownsBand($song->band_id)) {
            abort(403, 'Only band owners can delete songs');
        }

        $song->delete();

        return response()->json(['message' => 'Song deleted successfully']);
    }

}
