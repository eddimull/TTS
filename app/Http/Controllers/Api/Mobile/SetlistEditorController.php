<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Events;
use App\Models\EventSetlist;
use App\Models\SetlistSong;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        $band = $this->resolveBand($event);

        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'songs'                 => 'present|array',
            'songs.*.type'          => 'nullable|in:song,break',
            'songs.*.song_id'       => ['nullable', 'integer', Rule::exists('songs', 'id')->where('band_id', $band->id)],
            'songs.*.custom_title'  => 'nullable|string|max:255',
            'songs.*.custom_artist' => 'nullable|string|max:255',
            'songs.*.notes'         => 'nullable|string|max:1000',
            'status'                => 'sometimes|nullable|in:draft,ready',
        ]);

        DB::transaction(function () use ($validated, $event, $band) {
            $setlist = EventSetlist::firstOrCreate(
                ['event_id' => $event->id],
                ['band_id' => $band->id, 'status' => 'draft'],
            );

            if (isset($validated['status'])) {
                $setlist->update(['status' => $validated['status']]);
            }

            $setlist->songs()->delete();

            foreach ($validated['songs'] as $index => $entry) {
                $type = $entry['type'] ?? 'song';
                SetlistSong::create([
                    'setlist_id'    => $setlist->id,
                    'type'          => $type,
                    'song_id'       => $type === 'song' ? ($entry['song_id'] ?? null) : null,
                    'custom_title'  => $type === 'song' ? ($entry['custom_title'] ?? null) : null,
                    'custom_artist' => $type === 'song' ? ($entry['custom_artist'] ?? null) : null,
                    'position'      => $index + 1,
                    'notes'         => $entry['notes'] ?? null,
                ]);
            }
        });

        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();
        abort_unless($setlist, 500, 'Setlist not found after write');

        return response()->json($this->formatSetlist($setlist));
    }

    public function generate(Request $request, Events $event): JsonResponse
    {
        $band = $this->resolveBand($event);

        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'context' => 'nullable|string|max:2000',
        ]);

        $event->load(['type', 'eventMembers.rosterMember']);

        [$songs, $songsArray] = $this->buildSongsArray($band);

        if (empty($songsArray)) {
            return response()->json(['error' => 'No active songs in the band library.'], 422);
        }

        if (!config('services.anthropic.key')) {
            return response()->json(['error' => 'Anthropic API key not configured.'], 503);
        }

        $this->attachRosterMembers($event);

        $aiService = app(\App\Services\SetlistAiService::class);

        $attachmentImages = [];
        $attachments = $event->attachments()->get();
        if ($attachments->isNotEmpty()) {
            $attachmentImages = $aiService->buildImageBlocks($attachments);
        }

        $userId   = Auth::id();
        $eventKey = $event->key;
        $progress = fn (string $step, string $status, ?string $detail) =>
            \App\Events\SetlistGenerationProgress::dispatch($userId, $eventKey, $step, $status, $detail);

        $result = $aiService->generateSetlist($event, $songsArray, $validated['context'] ?? null, $attachmentImages, $progress);

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
                        'event_context' => $result['event_context'] ?? null,
                        'image_context' => $result['image_context'] ?? [],
                    ],
                ],
            );

            $setlist->songs()->delete();
            $this->saveSetlistItems($setlist, $finalItems);
        });

        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();
        abort_unless($setlist, 500, 'Setlist not found after generate');

        return response()->json($this->formatSetlist($setlist));
    }

    /**
     * Load the band's active songs (eager-loaded for the AI service) and return
     * both the Eloquent collection and the array form the AI service consumes.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array}
     */
    private function buildSongsArray(Bands $band): array
    {
        $songs = $band->songs()
            ->where('active', true)
            ->with(['leadSinger.user', 'transitionSong'])
            ->get();

        $songsArray = $songs->map(fn ($s) => [
            'id'              => $s->id,
            'title'           => $s->title,
            'artist'          => $s->artist,
            'song_key'        => $s->song_key,
            'genre'           => $s->genre,
            'bpm'             => $s->bpm,
            'energy'          => $s->energy,
            'lead_singer'     => $s->leadSinger?->display_name,
            'transition_song' => $s->transitionSong
                ? $s->transitionSong->title . ($s->transitionSong->artist ? ' – ' . $s->transitionSong->artist : '')
                : null,
        ])->all();

        return [$songs, $songsArray];
    }

    /**
     * Attach the event's roster members (name + role) to the model so the AI
     * service can reference who is playing. Expects eventMembers.rosterMember
     * to be loaded.
     */
    private function attachRosterMembers(Events $event): void
    {
        $event->roster_members = $event->eventMembers->map(fn ($m) => [
            'name' => $m->display_name,
            'role' => $m->role_name,
        ]);
    }

    // TODO: filterValidItems/saveSetlistItems duplicate App\Http\Controllers\SetlistController —
    // extract to a shared SetlistPersistence service in a follow-up (kept separate for now to keep this PR focused).
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
                SetlistSong::create([
                    'setlist_id' => $setlist->id,
                    'type'       => 'song',
                    'song_id'    => (int) $item['id'],
                    'notes'      => $item['note'] ?? 'Client request',
                    'position'   => $index + 1,
                ]);
            } elseif (is_array($item)) {
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

    public function refine(Request $request, Events $event): JsonResponse
    {
        $band = $this->resolveBand($event);

        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'message'           => 'required|string|max:2000',
            'history'           => 'sometimes|array',
            'history.*.role'    => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:10000',
        ]);

        // Refine only operates on an existing setlist — bail before doing any
        // eager loading or AI work if there's nothing to refine.
        $setlist = $event->setlist()->with('songs.song.leadSinger')->first();
        if (!$setlist) {
            return response()->json(['error' => 'No setlist exists yet. Generate one first.'], 422);
        }

        $event->load(['type', 'eventMembers.rosterMember']);

        [$songs, $songsArray] = $this->buildSongsArray($band);

        if (empty($songsArray)) {
            return response()->json(['error' => 'No active songs in the band library.'], 422);
        }

        $this->attachRosterMembers($event);

        $currentSetlist = $this->formatSetlist($setlist)['songs'];

        $aiService = app(\App\Services\SetlistAiService::class);
        $result    = $aiService->refineSetlist(
            $event,
            $songsArray,
            $currentSetlist,
            $validated['history'] ?? [],
            $validated['message'],
        );

        if (empty($result['setlist'])) {
            return response()->json(['error' => 'AI could not refine the setlist. Please try again.'], 500);
        }

        $songMap    = $songs->keyBy('id');
        $finalItems = $this->filterValidItems($result['setlist'], $songMap);

        DB::transaction(function () use ($setlist, $finalItems) {
            $setlist->songs()->delete();
            $this->saveSetlistItems($setlist, $finalItems);
        });

        $updatedSetlist = $event->setlist()->with('songs.song.leadSinger')->first();
        abort_unless($updatedSetlist, 500, 'Setlist not found after refine');

        return response()->json([
            'setlist' => $this->formatSetlist($updatedSetlist),
            'summary' => $result['summary'] ?? '',
        ]);
    }
}
