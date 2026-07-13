<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSongRequest;
use App\Http\Requests\UpdateSongRequest;
use App\Models\Bands;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SongsController extends Controller
{
    /**
     * List a band's songs. Active-only by default (search + setlist picker
     * behavior); pass include_inactive=1 for the management screen.
     */
    public function index(Request $request, Bands $band): JsonResponse
    {
        $query = Song::where('band_id', $band->id)
            ->with([
                'leadSinger.user',
                'transitionSong:id,title,artist',
                'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads'),
            ])
            ->orderBy('title');

        if (!$request->boolean('include_inactive')) {
            $query->where('active', true);
        }

        return response()->json([
            'songs' => $query->get()->map(fn (Song $s) => $this->songPayload($s))->values(),
            'genres' => Song::GENRES,
        ]);
    }

    public function store(StoreSongRequest $request, Bands $band): JsonResponse
    {
        $song = $band->songs()->create($request->validated());
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')]);

        return response()->json(['song' => $this->songPayload($song)], 201);
    }

    public function update(UpdateSongRequest $request, Bands $band, Song $song): JsonResponse
    {
        if ((int) $song->band_id !== (int) $band->id) {
            return response()->json(['message' => 'Song not found.'], 404);
        }

        $song->update($request->validated());
        $song->load(['leadSinger.user', 'transitionSong:id,title,artist', 'charts' => fn ($q) => $q->select('id', 'song_id', 'title')->without('uploads')]);

        return response()->json(['song' => $this->songPayload($song)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function songPayload(Song $song): array
    {
        return [
            'id' => $song->id,
            'band_id' => $song->band_id,
            'title' => $song->title ?? '',
            'artist' => $song->artist ?? '',
            'song_key' => $song->song_key ?? '',
            'genre' => $song->genre ?? '',
            'bpm' => $song->bpm ?? 0,
            'notes' => $song->notes ?? '',
            'rating' => $song->rating,
            'energy' => $song->energy,
            'active' => (bool) $song->active,
            'lead_singer' => $song->leadSinger ? [
                'id' => $song->leadSinger->id,
                'display_name' => $song->leadSinger->display_name,
            ] : null,
            'transition_song' => $song->transitionSong ? [
                'id' => $song->transitionSong->id,
                'title' => $song->transitionSong->title ?? '',
                'artist' => $song->transitionSong->artist ?? '',
            ] : null,
            'charts' => $song->charts->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title ?? '',
            ])->values(),
        ];
    }
}
