<?php

namespace App\Http\Controllers;

use App\Models\Events;
use App\Models\LiveSetlistSession;
use App\Services\LiveSetlistSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Response as InertiaResponse;

class LiveSessionController extends Controller
{
    public function show(string $key): InertiaResponse
    {
        $event = Events::where('key', $key)->firstOrFail();
        $event->load('eventable.band');
        $band = $event->eventable->band;

        if (!Auth::user()->canRead('events', $band->id)) {
            abort(403);
        }

        $session = $event->liveSetlistSession()
            ->with(['queue.song.leadSinger', 'captains.user', 'startedBy'])
            ->first();

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

        $isCaptain = $session && $session->isCaptain(Auth::user());

        return inertia('Setlists/Live', [
            'event' => [
                'id' => $event->id,
                'key' => $event->key,
                'title' => $event->title,
                'date' => $event->date,
            ],
            'session' => $session ? $this->formatSession($session) : null,
            'songs' => $songs,
            'isCaptain' => $isCaptain,
            'currentUserId' => Auth::id(),
            'canWrite' => Auth::user()->canWrite('events', $band->id),
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

        if ($event->liveSetlistSession()->whereIn('status', ['active', 'paused'])->exists()) {
            return response()->json(['error' => 'A session is already in progress.'], 422);
        }

        $service = new LiveSetlistSessionService();
        $setlist = $event->setlist()->with('songs')->first();

        if ($setlist && $setlist->songs->isNotEmpty()) {
            $session = $service->start($setlist, Auth::user());
        } else {
            $session = $service->startEmpty($event->id, $band->id, Auth::user());
        }

        return response()->json($this->formatSession($session));
    }

    public function end(string $key): JsonResponse
    {
        $event = Events::where('key', $key)->firstOrFail();
        $session = $event->liveSetlistSession()->whereIn('status', ['active', 'paused'])->firstOrFail();

        if (!$session->isCaptain(Auth::user())) {
            abort(403, 'Only captains can end the session.');
        }

        $service = new LiveSetlistSessionService();
        $service->end($session, Auth::user());

        return response()->json(['status' => 'completed']);
    }

    private function formatSession(LiveSetlistSession $session): array
    {
        $service = new LiveSetlistSessionService();
        return [
            'id' => $session->id,
            'status' => $session->status,
            'is_dynamic' => $session->is_dynamic,
            'current_position' => $session->current_position,
            'started_at' => $session->started_at,
            'queue' => $service->formatQueue($session),
            'captains' => $session->captains->map(fn($c) => [
                'user_id' => $c->user_id,
                'name' => $c->user?->name,
            ]),
        ];
    }
}
