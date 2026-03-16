<?php

namespace App\Http\Controllers;

use App\Models\Events;
use App\Models\EventSetlist;
use App\Models\SetlistSong;
use App\Services\SetlistAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Response as InertiaResponse;

class SetlistController extends Controller
{
    public function show(string $key): InertiaResponse
    {
        $event = Events::where('key', $key)->firstOrFail();
        $band = $event->eventable->band;

        if (!Auth::user()->canRead('events', $band->id)) {
            abort(403);
        }

        $event->load(['type', 'eventMembers.rosterMember']);

        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();

        $songs = $band->songs()
            ->where('active', true)
            ->with('leadSinger')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'artist' => $s->artist,
                'song_key' => $s->song_key,
                'genre' => $s->genre,
                'bpm' => $s->bpm,
                'lead_singer' => $s->leadSinger?->display_name,
            ]);

        return inertia('Setlists/Editor', [
            'event' => [
                'id' => $event->id,
                'key' => $event->key,
                'title' => $event->title,
                'date' => $event->date,
                'time' => $event->time,
                'type' => $event->type,
                'notes' => $event->notes,
                'roster_members' => $event->eventMembers->map(fn($m) => [
                    'name' => $m->display_name,
                    'role' => $m->role_name,
                ]),
            ],
            'setlist' => $setlist ? $this->formatSetlist($setlist) : null,
            'songs' => $songs,
            'canWrite' => Auth::user()->canWrite('events', $band->id),
        ]);
    }

    public function generate(Request $request, string $key): JsonResponse
    {
        $event = Events::where('key', $key)->firstOrFail();
        $band = $event->eventable->band;

        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        $event->load(['type', 'eventMembers.rosterMember', 'eventable']);

        $songs = $band->songs()
            ->where('active', true)
            ->with(['leadSinger', 'transitionSong'])
            ->get();

        $songsArray = $songs->map(fn($s) => [
            'id' => $s->id,
            'title' => $s->title,
            'artist' => $s->artist,
            'song_key' => $s->song_key,
            'genre' => $s->genre,
            'bpm' => $s->bpm,
            'lead_singer' => $s->leadSinger?->display_name,
            'transition_song' => $s->transitionSong
                ? $s->transitionSong->title . ($s->transitionSong->artist ? ' – ' . $s->transitionSong->artist : '')
                : null,
        ])->all();

        if (empty($songsArray)) {
            return response()->json(['error' => 'No active songs in the band library.'], 422);
        }

        if (!config('services.anthropic.key')) {
            return response()->json(['error' => 'Anthropic API key not configured.'], 503);
        }

        $event->roster_members = $event->eventMembers->map(fn($m) => [
            'name' => $m->display_name,
            'role' => $m->role_name,
        ]);

        $aiService = new SetlistAiService();
        $orderedIds = $aiService->generateSetlist($event, $songsArray, $request->input('context'));

        if (empty($orderedIds)) {
            return response()->json(['error' => 'AI could not generate a setlist. Please try again.'], 500);
        }

        // Build ordered songs from IDs, keeping only what the AI chose
        $songMap = $songs->keyBy('id');
        $finalOrder = collect($orderedIds)
            ->filter(fn($id) => $songMap->has($id))
            ->values();

        DB::transaction(function () use ($event, $band, $finalOrder, $songsArray) {
            $setlist = EventSetlist::updateOrCreate(
                ['event_id' => $event->id],
                [
                    'band_id' => $band->id,
                    'generated_at' => now(),
                    'status' => 'draft',
                    'ai_context' => [
                        'song_count' => count($songsArray),
                        'generated_at' => now()->toISOString(),
                    ],
                ]
            );

            $setlist->songs()->delete();

            $finalOrder->each(function ($songId, $index) use ($setlist) {
                SetlistSong::create([
                    'setlist_id' => $setlist->id,
                    'song_id' => $songId,
                    'position' => $index + 1,
                ]);
            });
        });

        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();

        return response()->json($this->formatSetlist($setlist));
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $event = Events::where('key', $key)->firstOrFail();
        $event->load('eventable.band');
        $band = $event->eventable->band;

        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'songs' => 'present|array',
            'songs.*.song_id' => 'nullable|integer|exists:songs,id',
            'songs.*.custom_title' => 'nullable|string|max:255',
            'songs.*.custom_artist' => 'nullable|string|max:255',
            'songs.*.notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|nullable|in:draft,ready',
        ]);

        DB::transaction(function () use ($validated, $event, $band) {
            $setlist = EventSetlist::firstOrCreate(
                ['event_id' => $event->id],
                ['band_id' => $band->id, 'status' => 'draft']
            );

            if (isset($validated['status'])) {
                $setlist->update(['status' => $validated['status']]);
            }

            $setlist->songs()->delete();

            foreach ($validated['songs'] as $index => $entry) {
                SetlistSong::create([
                    'setlist_id' => $setlist->id,
                    'song_id' => $entry['song_id'] ?? null,
                    'custom_title' => $entry['custom_title'] ?? null,
                    'custom_artist' => $entry['custom_artist'] ?? null,
                    'position' => $index + 1,
                    'notes' => $entry['notes'] ?? null,
                ]);
            }
        });

        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();

        return response()->json($this->formatSetlist($setlist));
    }

    private function formatSetlist(EventSetlist $setlist): array
    {
        return [
            'id' => $setlist->id,
            'status' => $setlist->status,
            'generated_at' => $setlist->generated_at,
            'songs' => $setlist->songs->map(fn($entry) => [
                'id' => $entry->id,
                'position' => $entry->position,
                'song_id' => $entry->song_id,
                'title' => $entry->display_title,
                'artist' => $entry->display_artist,
                'song_key' => $entry->song?->song_key,
                'genre' => $entry->song?->genre,
                'bpm' => $entry->song?->bpm,
                'lead_singer' => $entry->song?->leadSinger?->display_name,
                'notes' => $entry->notes,
            ]),
        ];
    }
}
