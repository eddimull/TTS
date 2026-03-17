<?php

namespace App\Http\Controllers;

use App\Models\LiveSetlistQueue;
use App\Models\LiveSetlistSession;
use App\Models\User;
use App\Services\LiveSetlistSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaptainController extends Controller
{
    private function session(int $id): LiveSetlistSession
    {
        $session = LiveSetlistSession::findOrFail($id);

        if (!$session->isCaptain(Auth::user())) {
            abort(403, 'Captain access required.');
        }

        return $session;
    }

    public function next(int $id): JsonResponse
    {
        $session = $this->session($id);
        (new LiveSetlistSessionService())->next($session, Auth::user());
        return response()->json(['ok' => true]);
    }

    public function reaction(Request $request, int $id): JsonResponse
    {
        $session = $this->session($id);

        $validated = $request->validate([
            'queue_entry_id' => 'required|integer|exists:live_setlist_queue,id',
            'reaction' => 'required|in:positive,negative,neutral',
        ]);

        $entry = LiveSetlistQueue::where('id', $validated['queue_entry_id'])
            ->where('session_id', $session->id)
            ->firstOrFail();

        (new LiveSetlistSessionService())->react($session, $entry, $validated['reaction'], Auth::user());

        return response()->json(['ok' => true]);
    }

    public function skip(int $id): JsonResponse
    {
        $session = $this->session($id);
        (new LiveSetlistSessionService())->skip($session, Auth::user());
        return response()->json(['ok' => true]);
    }

    public function skipRemove(int $id): JsonResponse
    {
        $session = $this->session($id);
        (new LiveSetlistSessionService())->skipRemove($session, Auth::user());
        return response()->json(['ok' => true]);
    }

    public function offSetlist(Request $request, int $id): JsonResponse
    {
        $session = $this->session($id);

        $validated = $request->validate([
            'song_id' => 'required|integer|exists:songs,id',
        ]);

        $entry = (new LiveSetlistSessionService())->addOffSetlist($session, $validated['song_id'], Auth::user());

        return response()->json([
            'id' => $entry->id,
            'title' => $entry->display_title,
            'artist' => $entry->display_artist,
        ]);
    }

    public function promote(Request $request, int $id): JsonResponse
    {
        $session = $this->session($id);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $target = User::findOrFail($validated['user_id']);
        (new LiveSetlistSessionService())->promoteCaptain($session, $target, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function demote(Request $request, int $id): JsonResponse
    {
        $session = $this->session($id);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $target = User::findOrFail($validated['user_id']);
        (new LiveSetlistSessionService())->demoteCaptain($session, $target, Auth::user());

        return response()->json(['ok' => true]);
    }
}
