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
            ->with('leadSinger.user')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'artist' => $s->artist,
                'song_key' => $s->song_key,
                'genre' => $s->genre,
                'bpm' => $s->bpm,
                'energy' => $s->energy,
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
            'bandId' => $band->id,
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
            ->with(['leadSinger.user', 'transitionSong'])
            ->get();

        $songsArray = $songs->map(fn($s) => [
            'id' => $s->id,
            'title' => $s->title,
            'artist' => $s->artist,
            'song_key' => $s->song_key,
            'genre' => $s->genre,
            'bpm' => $s->bpm,
            'energy' => $s->energy,
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

        $attachmentImages = [];
        $attachments = $event->attachments()->get();
        \Illuminate\Support\Facades\Log::info('SetlistController: attachments found', ['count' => $attachments->count(), 'event_id' => $event->id]);
        if ($attachments->isNotEmpty()) {
            $attachmentImages = $aiService->buildImageBlocks($attachments);
            \Illuminate\Support\Facades\Log::info('SetlistController: image blocks built', ['count' => count($attachmentImages)]);
        }

        $result = $aiService->generateSetlist($event, $songsArray, $request->input('context'), $attachmentImages);

        if (empty($result['items'])) {
            return response()->json(['error' => 'AI could not generate a setlist. Please try again.'], 500);
        }

        $songMap    = $songs->keyBy('id');
        $finalItems = $this->filterValidItems($result['items'], $songMap);

        DB::transaction(function () use ($event, $band, $finalItems, $songsArray, $result) {
            $setlist = EventSetlist::updateOrCreate(
                ['event_id' => $event->id],
                [
                    'band_id'      => $band->id,
                    'generated_at' => now(),
                    'status'       => 'draft',
                    'ai_context'   => [
                        'song_count'    => count($songsArray),
                        'generated_at'  => now()->toISOString(),
                        'event_context' => $result['event_context'],
                        'image_context' => $result['image_context'],
                    ],
                ]
            );

            $setlist->songs()->delete();
            $this->saveSetlistItems($setlist, $finalItems);
        });

        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();
        $formatted = $this->formatSetlist($setlist);
        \Illuminate\Support\Facades\Log::info('SetlistController: final order returned to frontend', [
            'songs' => collect($formatted['songs'])->map(fn($s) => $s['position'] . '. ' . $s['title'] . ' (' . ($s['lead_singer'] ?? 'none') . ')')->values()->all()
        ]);

        return response()->json($formatted);
    }

    public function refine(Request $request, string $key): JsonResponse
    {
        $event = Events::where('key', $key)->firstOrFail();
        $band  = $event->eventable->band;

        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'message'         => 'required|string|max:2000',
            'history'         => 'sometimes|array',
            'history.*.role'  => 'required|in:user,assistant',
            'history.*.content' => 'required|string',
        ]);

        $event->load(['type', 'eventMembers.rosterMember', 'eventable']);

        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();
        if (!$setlist) {
            return response()->json(['error' => 'No setlist exists yet. Generate one first.'], 422);
        }

        $songs = $band->songs()
            ->where('active', true)
            ->with(['leadSinger.user', 'transitionSong'])
            ->get();

        $songsArray = $songs->map(fn($s) => [
            'id'             => $s->id,
            'title'          => $s->title,
            'artist'         => $s->artist,
            'song_key'       => $s->song_key,
            'genre'          => $s->genre,
            'bpm'            => $s->bpm,
            'energy'         => $s->energy,
            'lead_singer'    => $s->leadSinger?->display_name,
            'transition_song' => $s->transitionSong
                ? $s->transitionSong->title . ($s->transitionSong->artist ? ' – ' . $s->transitionSong->artist : '')
                : null,
        ])->all();

        $event->roster_members = $event->eventMembers->map(fn($m) => [
            'name' => $m->display_name,
            'role' => $m->role_name,
        ]);

        $currentSetlist = $this->formatSetlist($setlist)['songs'];

        $aiService = new SetlistAiService();
        $result    = $aiService->refineSetlist(
            $event,
            $songsArray,
            $currentSetlist,
            $validated['history'] ?? [],
            $validated['message']
        );

        if (empty($result['setlist'])) {
            return response()->json(['error' => 'AI could not refine the setlist. Please try again.'], 500);
        }

        $songMap    = $songs->keyBy('id');
        $finalItems = $this->filterValidItems($result['setlist'], $songMap);

        DB::transaction(function () use ($event, $finalItems) {
            $setlist = $event->setlist()->firstOrFail();
            $setlist->songs()->delete();
            $this->saveSetlistItems($setlist, $finalItems);
        });

        $updatedSetlist = $event->setlist()->with('songs.song.leadSinger')->first();

        return response()->json([
            'setlist' => $this->formatSetlist($updatedSetlist),
            'summary' => $result['summary'],
        ]);
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
            'songs.*.type' => 'nullable|in:song,break',
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
                $type = $entry['type'] ?? 'song';
                SetlistSong::create([
                    'setlist_id' => $setlist->id,
                    'type' => $type,
                    'song_id' => $type === 'song' ? ($entry['song_id'] ?? null) : null,
                    'custom_title' => $type === 'song' ? ($entry['custom_title'] ?? null) : null,
                    'custom_artist' => $type === 'song' ? ($entry['custom_artist'] ?? null) : null,
                    'position' => $index + 1,
                    'notes' => $entry['notes'] ?? null,
                ]);
            }
        });

        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();

        return response()->json($this->formatSetlist($setlist));
    }

    /**
     * Reorder items so no lead singer appears more than once in any 3-song window.
     * Breaks are kept in place. Songs without a lead singer (instrumental/custom) are neutral.
     */
    private function interleaveSingers(\Illuminate\Support\Collection $items, \Illuminate\Support\Collection $songMap): \Illuminate\Support\Collection
    {
        $getSinger = function ($item) use ($songMap): ?string {
            if ($item === 'break') return null;
            $id = is_array($item) ? ($item['id'] ?? null) : $item;
            if (!$id) return null;
            $song = $songMap->get((int) $id);
            return $song?->leadSinger?->display_name;
        };

        // Split into sets (separated by breaks) and process each independently
        $sets = [];
        $current = [];
        foreach ($items->values()->all() as $item) {
            if ($item === 'break') {
                $sets[] = ['songs' => $current, 'break' => true];
                $current = [];
            } else {
                $current[] = $item;
            }
        }
        $sets[] = ['songs' => $current, 'break' => false];

        $processedSets = [];
        foreach ($sets as $set) {
            $result = $set['songs'];
            $maxPasses = count($result) * 2;
            $pass = 0;

            do {
                $swapped = false;
                for ($i = 0; $i < count($result) - 1; $i++) {
                    $recentSingers = [];
                    for ($j = $i; $j >= 0 && count($recentSingers) < 2; $j--) {
                        $s = $getSinger($result[$j]);
                        if ($s !== null) $recentSingers[] = $s;
                    }

                    $nextSinger = $getSinger($result[$i + 1]);
                    if ($nextSinger === null) continue;

                    if (count(array_filter($recentSingers, fn($s) => $s === $nextSinger)) >= 2) {
                        for ($k = $i + 2; $k < count($result); $k++) {
                            $candidateSinger = $getSinger($result[$k]);
                            if ($candidateSinger !== $nextSinger) {
                                [$result[$i + 1], $result[$k]] = [$result[$k], $result[$i + 1]];
                                $swapped = true;
                                break;
                            }
                        }
                    }
                }
                $pass++;
            } while ($swapped && $pass < $maxPasses);

            $processedSets[] = ['songs' => $result, 'break' => $set['break']];
        }

        // Reassemble with breaks
        $final = [];
        foreach ($processedSets as $set) {
            foreach ($set['songs'] as $song) {
                $final[] = $song;
            }
            if ($set['break']) {
                $final[] = 'break';
            }
        }

        return collect($final);
    }

    private function filterValidItems(array $orderedItems, \Illuminate\Support\Collection $songMap): \Illuminate\Support\Collection
    {
        $seenIds = [];

        return collect($orderedItems)
            ->filter(function ($item) use ($songMap, &$seenIds) {
                if ($item === 'break') return true;

                if (is_int($item) && $songMap->has($item)) {
                    if (in_array($item, $seenIds)) return false;
                    $seenIds[] = $item;
                    return true;
                }

                if (is_array($item) && isset($item['id']) && $songMap->has((int) $item['id'])) {
                    $id = (int) $item['id'];
                    if (in_array($id, $seenIds)) return false;
                    $seenIds[] = $id;
                    return true;
                }

                if (is_array($item) && !empty($item['title'])) return true;

                return false;
            })
            ->values();
    }

    private function saveSetlistItems(EventSetlist $setlist, \Illuminate\Support\Collection $items): void
    {
        $items->each(function ($item, $index) use ($setlist) {
            if ($item === 'break') {
                SetlistSong::create([
                    'setlist_id' => $setlist->id,
                    'type'       => 'break',
                    'position'   => $index + 1,
                ]);
            } elseif (is_array($item) && isset($item['id'])) {
                // Library song flagged as a client request
                SetlistSong::create([
                    'setlist_id' => $setlist->id,
                    'type'       => 'song',
                    'song_id'    => (int) $item['id'],
                    'notes'      => $item['note'] ?? 'Client request',
                    'position'   => $index + 1,
                ]);
            } elseif (is_array($item)) {
                // Custom song not in library
                SetlistSong::create([
                    'setlist_id'    => $setlist->id,
                    'type'          => 'song',
                    'song_id'       => null,
                    'custom_title'  => $item['title'],
                    'custom_artist' => $item['artist'] ?? null,
                    'notes'         => $item['note'] ?? 'Client request — not in library',
                    'position'      => $index + 1,
                ]);
            } else {
                SetlistSong::create([
                    'setlist_id' => $setlist->id,
                    'type'       => 'song',
                    'song_id'    => $item,
                    'position'   => $index + 1,
                ]);
            }
        });
    }

    private function formatSetlist(EventSetlist $setlist): array
    {
        $aiContext = $setlist->ai_context ?? [];

        return [
            'id'            => $setlist->id,
            'status'        => $setlist->status,
            'generated_at'  => $setlist->generated_at,
            'event_context' => $aiContext['event_context'] ?? null,
            'image_context' => $aiContext['image_context'] ?? [],
            'songs' => $setlist->songs->map(fn($entry) => [
                'id' => $entry->id,
                'type' => $entry->type ?? 'song',
                'position' => $entry->position,
                'song_id' => $entry->song_id,
                'title' => $entry->display_title,
                'artist' => $entry->display_artist,
                'song_key' => $entry->song?->song_key,
                'genre' => $entry->song?->genre,
                'bpm' => $entry->song?->bpm,
                'energy' => $entry->song?->energy,
                'lead_singer' => $entry->song?->leadSinger?->display_name,
                'notes' => $entry->notes,
            ])->values()->all(),
        ];
    }
}
