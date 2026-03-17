<?php

namespace App\Http\Controllers;

use App\Models\LiveSetlistSession;
use App\Services\LiveSetlistSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BreakController extends Controller
{
    private function session(int $id): LiveSetlistSession
    {
        $session = LiveSetlistSession::findOrFail($id);

        if (!$session->isCaptain(Auth::user())) {
            abort(403, 'Captain access required.');
        }

        return $session;
    }

    public function start(int $id): JsonResponse
    {
        $session = $this->session($id);

        if ($session->status !== 'active') {
            return response()->json(['error' => 'Session is not active.'], 422);
        }

        (new LiveSetlistSessionService())->startBreak($session, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $session = $this->session($id);

        if ($session->status !== 'break') {
            return response()->json(['error' => 'Session is not on break.'], 422);
        }

        $request->validate(['song_id' => 'required|integer|exists:songs,id']);

        (new LiveSetlistSessionService())->resumeFromBreak($session, $request->song_id, Auth::user());

        return response()->json(['ok' => true]);
    }
}
