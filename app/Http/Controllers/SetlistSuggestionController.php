<?php

namespace App\Http\Controllers;

use App\Events\SetlistQueueingNext;
use App\Models\LiveSetlistSession;
use App\Services\LiveSetlistSessionService;
use App\Services\SetlistAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetlistSuggestionController extends Controller
{
    public function suggest(Request $request, int $id): JsonResponse
    {
        $session = LiveSetlistSession::findOrFail($id);

        if (!$session->isCaptain(Auth::user())) {
            abort(403);
        }

        $session->load(['queue.song', 'event.type', 'event.eventMembers.rosterMember', 'event.eventable']);

        if (!config('services.anthropic.key')) {
            return response()->json(['error' => 'Anthropic API key not configured.'], 503);
        }

        // Songs already used (played, skipped-removed, or currently queued)
        $usedSongIds = $session->queue->pluck('song_id')->filter()->values()->all();

        // Enforce transition rule: if the current song mandates a transition, return it immediately
        $currentEntry = $session->queue
            ->where('position', $session->current_position)
            ->where('status', 'pending')
            ->first();
        $currentSong = $currentEntry?->song?->load('transitionSong');
        if ($currentSong?->transition_song_id && !in_array($currentSong->transition_song_id, $usedSongIds)) {
            $transition = $currentSong->transitionSong->load('leadSinger');
            return response()->json([
                'suggestion' => [
                    'song_id'     => $transition->id,
                    'title'       => $transition->title,
                    'artist'      => $transition->artist,
                    'song_key'    => $transition->song_key,
                    'genre'       => $transition->genre,
                    'bpm'         => $transition->bpm,
                    'lead_singer' => $transition->leadSinger?->display_name,
                ],
                'forced_transition' => true,
            ]);
        }

        // Track rejected suggestions — append exclude param and persist
        $excludeId = $request->integer('exclude', 0);
        $rejectedIds = $session->rejected_song_ids ?? [];
        if ($excludeId && !in_array($excludeId, $rejectedIds)) {
            $rejectedIds[] = $excludeId;
            // If all non-used songs have been rejected, reset so they cycle back
            $band = $session->event->eventable->band;
            $totalAvailable = $band->songs()->where('active', true)->whereNotIn('id', $usedSongIds)->count();
            if (count($rejectedIds) >= $totalAvailable) {
                $rejectedIds = [];
            }
            $session->update(['rejected_song_ids' => $rejectedIds]);
        }

        // All active band songs not yet used
        $band = $band ?? $session->event->eventable->band;
        $allSongs = $band->songs()
            ->where('active', true)
            ->whereNotIn('id', $usedSongIds)
            ->with(['leadSinger', 'transitionSong'])
            ->get()
            ->map(fn($s) => [
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
            ]);

        if ($allSongs->isEmpty()) {
            return response()->json(['suggestion' => null, 'message' => 'No more songs available.']);
        }

        // Remove rejected songs entirely — AI only sees songs it hasn't been told to skip
        // (rejected list resets above when all songs are exhausted, so they cycle back)
        $availableSongs = $allSongs
            ->filter(fn($s) => !in_array($s['id'], $rejectedIds))
            ->values()
            ->all();

        // Build reaction log from played queue entries
        $reactionLog = $session->queue
            ->where('status', 'played')
            ->whereNotNull('crowd_reaction')
            ->map(fn($e) => [
                'title' => $e->song?->title ?? $e->custom_title,
                'genre' => $e->song?->genre,
                'reaction' => $e->crowd_reaction,
            ])->values()->all();

        $playedSongs = $session->queue
            ->whereIn('status', ['played', 'skipped'])
            ->map(fn($e) => [
                'title' => $e->song?->title ?? $e->custom_title,
                'artist' => $e->song?->artist ?? $e->custom_artist,
            ])->values()->all();

        $event = $session->event;
        $event->roster_members = $event->eventMembers->map(fn($m) => [
            'name' => $m->display_name,
            'role' => $m->role_name,
        ]);

        $aiService = new SetlistAiService();
        $suggestedId = $aiService->suggestNext($event, $availableSongs, $playedSongs, $reactionLog, (bool) $session->after_break)
            ?? $availableSongs[0]['id'];
        $song = $band->songs()->with('leadSinger')->find($suggestedId);

        if (!$song) {
            return response()->json(['suggestion' => null]);
        }

        return response()->json([
            'suggestion' => [
                'song_id' => $song->id,
                'title' => $song->title,
                'artist' => $song->artist,
                'song_key' => $song->song_key,
                'genre' => $song->genre,
                'bpm' => $song->bpm,
                'lead_singer' => $song->leadSinger?->display_name,
            ],
        ]);
    }

    public function queuingNext(int $id): JsonResponse
    {
        $session = LiveSetlistSession::findOrFail($id);

        if (!$session->isCaptain(Auth::user())) {
            abort(403);
        }

        broadcast(new SetlistQueueingNext($session));

        return response()->json(['ok' => true]);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $session = LiveSetlistSession::findOrFail($id);

        if (!$session->isCaptain(Auth::user())) {
            abort(403);
        }

        $request->validate(['song_id' => 'required|integer|exists:songs,id']);

        $service = new LiveSetlistSessionService();
        $entry = $service->acceptSuggestion($session, $request->song_id, Auth::user());

        if ($session->after_break) {
            $session->update(['after_break' => false]);
        }

        return response()->json([
            'entry' => [
                'id' => $entry->id,
                'position' => $entry->position,
                'status' => $entry->status,
                'title' => $entry->display_title,
                'artist' => $entry->display_artist,
                'song_key' => $entry->song?->song_key,
                'genre' => $entry->song?->genre,
                'bpm' => $entry->song?->bpm,
                'lead_singer' => $entry->song?->leadSinger?->display_name,
                'crowd_reaction' => null,
                'is_off_setlist' => false,
            ],
        ]);
    }

    public function replaceNext(Request $request, int $id): JsonResponse
    {
        $session = LiveSetlistSession::findOrFail($id);

        if (!$session->isCaptain(Auth::user())) {
            abort(403);
        }

        $request->validate(['song_id' => 'required|integer|exists:songs,id']);

        $service = new LiveSetlistSessionService();
        $service->replaceNextSong($session, $request->song_id, Auth::user());

        return response()->json(['ok' => true]);
    }
}
