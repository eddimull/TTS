<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Events;
use App\Models\EventSetlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetlistEditorController extends Controller
{
    public function show(Events $event): JsonResponse
    {
        $band = $this->resolveBand($event);

        if (!Auth::user()->canRead('events', $band->id)) {
            abort(403);
        }

        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();

        $songs = $band->songs()
            ->where('active', true)
            ->with('leadSinger')
            ->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'title'       => $s->title,
                'artist'      => $s->artist,
                'song_key'    => $s->song_key,
                'genre'       => $s->genre,
                'bpm'         => $s->bpm,
                'energy'      => $s->energy,
                'lead_singer' => $s->leadSinger?->display_name,
            ]);

        return response()->json([
            'event' => [
                'id'    => $event->id,
                'key'   => $event->key,
                'title' => $event->title,
            ],
            'setlist'   => $setlist ? $this->formatSetlist($setlist) : null,
            'songs'     => $songs,
            'can_write' => Auth::user()->canWrite('events', $band->id),
        ]);
    }

    /**
     * Resolve the band that owns this event, or 404 if the event has no
     * eventable/band (e.g. an orphaned row). These routes are gated in the
     * controller (not by the mobile.band middleware) because the route key is
     * {event}, not {band} — so every action must call this + a canRead/canWrite
     * check before doing work.
     */
    private function resolveBand(Events $event): Bands
    {
        $event->loadMissing('eventable.band');
        $band = $event->eventable?->band;

        if (!$band) {
            abort(404);
        }

        return $band;
    }

    private function formatSetlist(EventSetlist $setlist): array
    {
        $aiContext = $setlist->ai_context ?? [];

        return [
            'id'            => $setlist->id,
            'status'        => $setlist->status,
            'generated_at'  => $setlist->generated_at?->toIso8601String(),
            'event_context' => $aiContext['event_context'] ?? null,
            'image_context' => $aiContext['image_context'] ?? [],
            'songs'         => $setlist->songs->map(fn ($entry) => [
                'id'            => $entry->id,
                'type'          => $entry->type ?? 'song',
                'position'      => $entry->position,
                'song_id'       => $entry->song_id,
                'title'         => $entry->display_title,
                'artist'        => $entry->display_artist,
                'custom_title'  => $entry->custom_title,
                'custom_artist' => $entry->custom_artist,
                'song_key'      => $entry->song?->song_key,
                'genre'         => $entry->song?->genre,
                'bpm'           => $entry->song?->bpm,
                'energy'        => $entry->song?->energy,
                'lead_singer'   => $entry->song?->leadSinger?->display_name,
                'notes'         => $entry->notes,
            ])->values()->all(),
        ];
    }

    public function update(Request $request, Events $event): JsonResponse
    {
        return response()->json([]);
    }

    public function generate(Request $request, Events $event): JsonResponse
    {
        return response()->json([]);
    }

    public function refine(Request $request, Events $event): JsonResponse
    {
        return response()->json([]);
    }
}
