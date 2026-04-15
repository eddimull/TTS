<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\BreakResumeRequest;
use App\Http\Requests\Mobile\OffSetlistRequest;
use App\Http\Requests\Mobile\SetlistCaptainRequest;
use App\Http\Requests\Mobile\SetlistReactionRequest;
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
    public function __construct(private readonly LiveSetlistSessionService $sessionService) {}

    // ── Session ────────────────────────────────────────────────────────────────

    public function show(Events $event): JsonResponse
    {
        $event->loadMissing('eventable.band');
        $band = $event->eventable->band;

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

    public function start(Events $event): JsonResponse
    {
        $event->loadMissing('eventable.band');
        $band = $event->eventable->band;

        if ($event->liveSetlistSession()->whereIn('status', ['active', 'paused', 'break'])->exists()) {
            return response()->json(['error' => 'A session is already in progress.'], 422);
        }

        $setlist = $event->setlist()->with('songs')->first();

        if ($setlist && $setlist->songs->isNotEmpty()) {
            $session = $this->sessionService->start($setlist, Auth::user());
        } else {
            $session = $this->sessionService->startEmpty($event->id, $band->id, Auth::user());
        }

        $session->load(['queue.song.leadSinger', 'captains.user']);

        return response()->json($this->formatSession($session));
    }

    public function end(Events $event): JsonResponse
    {
        $session = $event->liveSetlistSession()
            ->whereIn('status', ['active', 'paused', 'break'])
            ->firstOrFail();

        if (!$session->isCaptain(Auth::user())) {
            abort(403, 'Only captains can end the session.');
        }

        $this->sessionService->end($session, Auth::user());

        return response()->json(['status' => 'completed']);
    }

    // ── Captain actions ────────────────────────────────────────────────────────

    public function next(int $id): JsonResponse
    {
        $this->sessionService->next($this->captainSession($id), Auth::user());
        return response()->json(['ok' => true]);
    }

    public function skip(int $id): JsonResponse
    {
        $this->sessionService->skip($this->captainSession($id), Auth::user());
        return response()->json(['ok' => true]);
    }

    public function skipRemove(int $id): JsonResponse
    {
        $this->sessionService->skipRemove($this->captainSession($id), Auth::user());
        return response()->json(['ok' => true]);
    }

    public function reaction(SetlistReactionRequest $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        $validated = $request->validated();

        $entry = LiveSetlistQueue::where('id', $validated['queue_entry_id'])
            ->where('session_id', $session->id)
            ->firstOrFail();

        $this->sessionService->react($session, $entry, $validated['reaction'], Auth::user());

        return response()->json(['ok' => true]);
    }

    public function offSetlist(OffSetlistRequest $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        $validated = $request->validated();

        $entry = $this->sessionService->addOffSetlist($session, $validated['song_id'], Auth::user());

        return response()->json([
            'id'     => $entry->id,
            'title'  => $entry->display_title,
            'artist' => $entry->display_artist,
        ]);
    }

    public function promote(SetlistCaptainRequest $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        $validated = $request->validated();

        $target = User::findOrFail($validated['user_id']);
        $this->sessionService->promoteCaptain($session, $target, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function demote(SetlistCaptainRequest $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        $validated = $request->validated();

        $target = User::findOrFail($validated['user_id']);
        $this->sessionService->demoteCaptain($session, $target, Auth::user());

        return response()->json(['ok' => true]);
    }

    // ── Break management ───────────────────────────────────────────────────────

    public function breakStart(int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        if ($session->status !== 'active') {
            return response()->json(['error' => 'Session is not active.'], 422);
        }

        $this->sessionService->startBreak($session, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function breakResume(BreakResumeRequest $request, int $id): JsonResponse
    {
        $session = $this->captainSession($id);

        if ($session->status !== 'break') {
            return response()->json(['error' => 'Session is not on break.'], 422);
        }

        $this->sessionService->resumeFromBreak($session, $request->song_id, Auth::user());

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
        return [
            'id'               => $session->id,
            'status'           => $session->status,
            'is_dynamic'       => (bool) $session->is_dynamic,
            'current_position' => $session->current_position,
            'started_at'       => $session->started_at?->toIso8601String(),
            'break_started_at' => $session->break_started_at?->toIso8601String(),
            'after_break'      => (bool) $session->after_break,
            'queue'            => $this->sessionService->formatQueue($session),
            'captains'         => $session->captains->map(fn ($c) => [
                'user_id' => $c->user_id,
                'name'    => $c->user?->name,
            ]),
        ];
    }
}
