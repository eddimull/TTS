<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\Song;
use App\Services\GetSongBpmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response as InertiaResponse;

class SongsController extends Controller
{
    private const GENRES = [
        'Blues', 'Country', 'Funk', 'Hip Hop', 'Jazz', 'Latin',
        'Pop', 'R&B', 'Rock', 'Soul',
    ];

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
                'genres' => self::GENRES,
                'availableBands' => [],
                'canWrite' => false,
            ]);
        }

        $band = Bands::findOrFail($currentBandId);

        if (!$band->everyone()->contains('user_id', $user->id) && !$user->isSubOfBand($band->id)) {
            abort(403, 'Unauthorized');
        }

        $songs = $band->songs()
            ->with(['leadSinger.user', 'transitionSong:id,title,artist'])
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
            'genres' => self::GENRES,
            'availableBands' => $bands->map(fn($b) => ['id' => $b->id, 'name' => $b->name]),
            'canWrite' => $user->canWrite('songs', $band->id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'band_id' => 'required|integer|exists:bands,id',
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'song_key' => 'nullable|string|max:20',
            'genre' => 'nullable|string|max:100',
            'bpm' => 'nullable|integer|min:1|max:999',
            'rating' => 'nullable|integer|min:1|max:10',
            'energy' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
            'lead_singer_id' => 'nullable|integer|exists:roster_members,id',
            'transition_song_id' => 'nullable|integer|exists:songs,id',
            'active' => 'boolean',
        ]);

        $band = Bands::findOrFail($validated['band_id']);

        if (!Auth::user()->canWrite('songs', $band->id)) {
            abort(403, 'Permission denied');
        }

        $song = $band->songs()->create($validated);
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist']);

        return response()->json($song, 201);
    }

    public function update(Request $request, Song $song): JsonResponse
    {
        if (!Auth::user()->canWrite('songs', $song->band_id)) {
            abort(403, 'Permission denied');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'song_key' => 'nullable|string|max:20',
            'genre' => 'nullable|string|max:100',
            'bpm' => 'nullable|integer|min:1|max:999',
            'rating' => 'nullable|integer|min:1|max:10',
            'energy' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
            'lead_singer_id' => 'nullable|integer|exists:roster_members,id',
            'transition_song_id' => 'nullable|integer|exists:songs,id',
            'active' => 'boolean',
        ]);

        $song->update($validated);
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist']);

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
