<?php

namespace App\Http\Controllers;

use App\Events\SetlistQueueingNext;
use App\Models\Bookings;
use App\Models\LiveSetlistSession;
use App\Services\ClientSongRequestsService;
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

        // Songs already used: played or still queued. Removed / skipped
        // entries never got played, so they're fair game again — same rule
        // the captain's picker uses.
        $usedSongIds = $session->queue
            ->whereIn('status', ['pending', 'played'])
            ->pluck('song_id')->filter()->values()->all();

        // Client questionnaire picks: do-not-play songs are never suggested;
        // must-play songs are flagged for the AI to work in.
        $clientRequests = $session->event->eventable instanceof Bookings
            ? app(ClientSongRequestsService::class)->forBooking($session->event->eventable)
            : null;
        $doNotPlayIds = $clientRequests['do_not_play'] ?? [];
        $mustPlayIds = $clientRequests['must_play'] ?? [];

        // Plus what the AI read out of the questionnaire's free text.
        $notes = $session->client_notes ?? [];
        foreach ($notes['requests'] ?? [] as $r) {
            if (!$r['song_id']) {
                continue;
            }
            if ($r['kind'] === 'do_not_play') {
                $doNotPlayIds[] = $r['song_id'];
            } elseif (in_array($r['kind'], ['must_play', 'special_moment'], true)) {
                $mustPlayIds[] = $r['song_id'];
            }
        }
        $mustPlayIds = array_values(array_diff(array_unique($mustPlayIds), $doNotPlayIds));
        $clientGuidance = $this->clientGuidance($notes);

        // Enforce transition rule: if the current song mandates a transition, return it immediately
        $currentEntry = $session->queue
            ->where('position', $session->current_position)
            ->where('status', 'pending')
            ->first();
        $currentSong = $currentEntry?->song?->load('transitionSong');
        if ($currentSong?->transition_song_id
            && !in_array($currentSong->transition_song_id, $usedSongIds)
            && !in_array($currentSong->transition_song_id, $doNotPlayIds)) {
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
            $totalAvailable = $band->songs()->where('active', true)
                ->whereNotIn('id', [...$usedSongIds, ...$doNotPlayIds])->count();
            if (count($rejectedIds) >= $totalAvailable) {
                $rejectedIds = [];
            }
            $session->update(['rejected_song_ids' => $rejectedIds]);
        }

        // All active band songs not yet used
        $band = $band ?? $session->event->eventable->band;
        $allSongs = $band->songs()
            ->where('active', true)
            ->whereNotIn('id', [...$usedSongIds, ...$doNotPlayIds])
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

        // Client requests that aren't in the catalog — still playable. Only
        // must-play / if-time ones: special moments belong to the timeline.
        $queuedTitles = $session->queue
            ->whereIn('status', ['pending', 'played'])
            ->whereNull('song_id')
            ->map(fn ($e) => mb_strtolower(trim((string) $e->custom_title)))
            ->all();
        $skipTitles = collect((array) $request->input('skip_titles', []))
            ->filter(fn ($t) => is_string($t))
            ->map(fn ($t) => mb_strtolower(trim($t)))
            ->all();
        $requestCandidates = collect($notes['requests'] ?? [])
            ->filter(fn ($r) => !$r['song_id'] && in_array($r['kind'], ['must_play', 'if_time'], true))
            ->reject(fn ($r) => in_array(mb_strtolower(trim($r['title'])), $queuedTitles, true));
        // Same "different one" round as `skip`, starting over once all shown.
        $freshCandidates = $requestCandidates
            ->reject(fn ($r) => in_array(mb_strtolower(trim($r['title'])), $skipTitles, true));
        if ($freshCandidates->isNotEmpty()) {
            $requestCandidates = $freshCandidates;
        }
        $requestCandidates = $requestCandidates->values()
            ->map(fn ($r, $i) => [
                'ref' => 'R' . ($i + 1),
                'title' => $r['title'],
                'artist' => $r['artist'],
                'kind' => $r['kind'],
                'note' => $r['note'],
            ])->all();

        if ($allSongs->isEmpty() && !$requestCandidates) {
            return response()->json(['suggestion' => null, 'message' => 'No more songs available.']);
        }

        // Remove rejected songs entirely — AI only sees songs it hasn't been told to skip
        // (rejected list resets above when all songs are exhausted, so they cycle back)
        $availableSongs = $allSongs
            ->filter(fn($s) => !in_array($s['id'], $rejectedIds))
            ->values()
            ->all();

        // "Give me a different one": skip songs already shown this round,
        // without remembering them for the rest of the night (unlike
        // `exclude`). Once everything has been shown, start over.
        $skipIds = collect((array) $request->input('skip', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($skipIds) {
            $fresh = array_values(array_filter($availableSongs, fn ($s) => !in_array($s['id'], $skipIds)));
            if ($fresh) {
                $availableSongs = $fresh;
            }
        }

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
        $suggestedId = $aiService->suggestNext(
            $event,
            $availableSongs,
            $playedSongs,
            $reactionLog,
            (bool) $session->after_break,
            array_values(array_intersect($mustPlayIds, array_column($availableSongs, 'id'))),
            $clientGuidance,
            $requestCandidates,
        );
        if ($suggestedId === SetlistAiService::NO_FIT) {
            return response()->json([
                'suggestion' => null,
                'message' => "Nothing left that fits the client's requests.",
            ]);
        }
        if (is_string($suggestedId)) {
            $pick = collect($requestCandidates)->firstWhere('ref', $suggestedId);

            return response()->json([
                'suggestion' => [
                    'song_id' => null,
                    'custom' => true,
                    'must_play' => $pick['kind'] === 'must_play',
                    'title' => $pick['title'],
                    'artist' => $pick['artist'],
                    'note' => $pick['note'],
                ],
            ]);
        }
        if (!$availableSongs) {
            // Only off-catalog requests left and the model didn't answer.
            $pick = $requestCandidates[0];

            return response()->json([
                'suggestion' => [
                    'song_id' => null,
                    'custom' => true,
                    'must_play' => $pick['kind'] === 'must_play',
                    'title' => $pick['title'],
                    'artist' => $pick['artist'],
                    'note' => $pick['note'],
                ],
            ]);
        }
        $suggestedId ??= $availableSongs[0]['id'];
        // Never trust the model to stay inside the list.
        if (!in_array($suggestedId, array_column($availableSongs, 'id'))) {
            $suggestedId = $availableSongs[0]['id'];
        }
        $song = $band->songs()->with('leadSinger')->find($suggestedId);

        if (!$song) {
            return response()->json(['suggestion' => null]);
        }

        return response()->json([
            'suggestion' => [
                'song_id' => $song->id,
                'must_play' => in_array($song->id, $mustPlayIds),
                'title' => $song->title,
                'artist' => $song->artist,
                'song_key' => $song->song_key,
                'genre' => $song->genre,
                'bpm' => $song->bpm,
                'lead_singer' => $song->leadSinger?->display_name,
            ],
        ]);
    }

    /** Rules, crowd and do-not-play artists from the questionnaire, for the prompt. */
    private function clientGuidance(array $notes): string
    {
        $lines = [];
        foreach ($notes['rules'] ?? [] as $rule) {
            $lines[] = "- {$rule}";
        }
        $avoid = collect($notes['requests'] ?? [])
            ->where('kind', 'do_not_play')
            ->map(fn ($r) => $r['title'] . ($r['artist'] ? " – {$r['artist']}" : ''))
            ->implode(', ');
        if ($avoid !== '') {
            $lines[] = "- Client said do NOT play: {$avoid}";
        }
        if (!empty($notes['crowd'])) {
            $lines[] = "- Crowd: {$notes['crowd']}";
        }

        return implode("\n", $lines);
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
