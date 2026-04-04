<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\LiveSetlistQueue;
use App\Models\LiveSetlistSession;
use App\Models\User;
use App\Services\LiveSetlistSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetlistController extends Controller
{
    // ── Session ────────────────────────────────────────────────────────────────

    public function show(string $key): JsonResponse
    {
        $event = Events::where('key', $key)->firstOrFail();
        $event->load('eventable.band');
        $band = $event->eventable->band;

        if (!Auth::user()->canRead('events', $band->id)) {
            abort(403);
        }

        $session = $event->liveSetlistSession()
            ->with(['queue.song.leadSinger', 'captains.user', 'startedBy'])
            ->whereIn('status', ['active', 'paused', 'break'])
            ->first();

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
                'lead_singer' => $s->leadSinger?->display_name,
            ]);

        return response()->json([
            'event' => [
                'id'    => $event->id,
                'key'   => $event->key,
                'title' => $event->title,
            ],
            'session'          => $session ? $this->formatSession($session) : null,
            'songs'            => $songs,
            'is_captain'       => $session && $session->isCaptain(Auth::user()),
            'can_write'        => Auth::user()->canWrite('events', $band->id),
            'current_user_id'  => Auth::id(),
        ]);
    }

    public function start(string $key): JsonResponse
    {
        $event = Events::where('key', $key)->firstOrFail();
        $event->load('eventable.band');
        $band = $event->eventable->band;

        if (!Auth::user()->canWrite('events', $band->id)) {
            abort(403);
        }

        if ($event->liveSetlistSession()->whereIn('status', ['active', 'paused', 'break'])->exists()) {
            return response()->json(['error' => 'A session is already in progress.'], 422);
        }

        $service = new LiveSetlistSessionService();
        $setlist = $event->setlist()->with('songs')->first();

        if ($setlist && $setlist->songs->isNotEmpty()) {
            $session = $service->start($setlist, Auth::user());
        } else {
            $session = $service->startEmpty($event->id, $band->id, Auth::user());
        }

        $session->load(['queue.song.leadSinger', 'captains.user']);

        return response()->json($this->formatSession($session));
    }

    public function end(string $key): JsonResponse
    {
        $event = Events::where('key', $key)->firstOrFail();
        $session = $event->liveSetlistSession()
            ->whereIn('status', ['active', 'paused', 'break'])
            ->firstOrFail();

        if (!$session->isCaptain(Auth::user())) {
            abort(403, 'Only captains can end the session.');
        }

        (new LiveSetlistSessionService())->end($session, Auth::user());

        return response()->json(['status' => 'completed']);
    }

    // ── Captain actions ────────────────────────────────────────────────────────

    public function next(int $id): JsonResponse
    {
        (new LiveSetlistSessionService())->next($this->captainSession($id), Auth::user());
        return response()->json(['ok' => true]);
    }

    public function skip(int $id): JsonResponse
    {
        (new LiveSetlistSessionService())->skip($this->captainSession($id), Auth::user());
        return response()->json(['ok' => true]);
    }

    public function skipRemove(int $id): JsonResponse
    {
        (new LiveSetlistSessionService())->skipRemove($this->captainSession($id), Auth::user());
        return response()->json(['ok' => true]);
    }

    public function reaction(Request $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        $validated = $request->validate([
            'queue_entry_id' => 'required|integer|exists:live_setlist_queue,id',
            'reaction'       => 'required|in:positive,negative,neutral',
        ]);

        $entry = LiveSetlistQueue::where('id', $validated['queue_entry_id'])
            ->where('session_id', $session->id)
            ->firstOrFail();

        (new LiveSetlistSessionService())->react($session, $entry, $validated['reaction'], Auth::user());

        return response()->json(['ok' => true]);
    }

    public function offSetlist(Request $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        $validated = $request->validate([
            'song_id' => 'required|integer|exists:songs,id',
        ]);

        $entry = (new LiveSetlistSessionService())->addOffSetlist($session, $validated['song_id'], Auth::user());

        return response()->json([
            'id'     => $entry->id,
            'title'  => $entry->display_title,
            'artist' => $entry->display_artist,
        ]);
    }

    public function promote(Request $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $target = User::findOrFail($validated['user_id']);
        (new LiveSetlistSessionService())->promoteCaptain($session, $target, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function demote(Request $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $target = User::findOrFail($validated['user_id']);
        (new LiveSetlistSessionService())->demoteCaptain($session, $target, Auth::user());

        return response()->json(['ok' => true]);
    }

    // ── Break management ───────────────────────────────────────────────────────

    public function breakStart(int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        if ($session->status !== 'active') {
            return response()->json(['error' => 'Session is not active.'], 422);
        }

        (new LiveSetlistSessionService())->startBreak($session, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function breakResume(Request $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        if ($session->status !== 'break') {
            return response()->json(['error' => 'Session is not on break.'], 422);
        }

        $request->validate(['song_id' => 'required|integer|exists:songs,id']);

        (new LiveSetlistSessionService())->resumeFromBreak($session, $request->song_id, Auth::user());

        return response()->json(['ok' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function captainSession(int $id): LiveSetlistSession
    {
        $session = LiveSetlistSession::findOrFail($id);

        if (!$session->isCaptain(Auth::user())) {
            abort(403, 'Captain access required.');
        }

        return $session;
    }

    private function formatSession(LiveSetlistSession $session): array
    {
        $service = new LiveSetlistSessionService();

        return [
            'id'               => $session->id,
            'status'           => $session->status,
            'is_dynamic'       => (bool) $session->is_dynamic,
            'current_position' => $session->current_position,
            'started_at'       => $session->started_at?->toIso8601String(),
            'break_started_at' => $session->break_started_at?->toIso8601String(),
            'after_break'      => (bool) $session->after_break,
            'queue'            => $service->formatQueue($session),
            'captains'         => $session->captains->map(fn ($c) => [
                'user_id' => $c->user_id,
                'name'    => $c->user?->name,
            ]),
        ];
    }
}
