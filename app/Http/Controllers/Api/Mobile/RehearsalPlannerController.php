<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Jobs\RehearsalPlannerTurnJob;
use App\Models\Bands;
use App\Models\RehearsalPlannerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RehearsalPlannerController extends Controller
{
    public function start(Bands $band): JsonResponse
    {
        if ($guard = $this->keyGuard()) {
            return $guard;
        }

        $session = RehearsalPlannerSession::create([
            'band_id' => $band->id,
            'user_id' => Auth::id(),
        ]);

        $assistant = $session->messages()->create([
            'role'   => 'assistant',
            'status' => 'streaming',
        ]);

        RehearsalPlannerTurnJob::dispatch($session->id, $assistant->id, null);

        return response()->json([
            'session_id'           => $session->id,
            'channel'              => 'private-rehearsal-planner.' . $session->id,
            'assistant_message_id' => $assistant->id,
        ]);
    }

    public function message(Request $request, Bands $band, RehearsalPlannerSession $session): JsonResponse
    {
        abort_unless($session->band_id === $band->id, 404);

        if ($guard = $this->keyGuard()) {
            return $guard;
        }

        $validated = $request->validate(['text' => 'required|string|max:4000']);

        $user = $session->messages()->create([
            'role'    => 'user',
            'content' => $validated['text'],
            'status'  => 'complete',
        ]);

        $assistant = $session->messages()->create([
            'role'   => 'assistant',
            'status' => 'streaming',
        ]);

        RehearsalPlannerTurnJob::dispatch($session->id, $assistant->id, $validated['text']);

        return response()->json([
            'user_message'         => $this->formatMessage($user),
            'assistant_message_id' => $assistant->id,
            'channel'              => 'private-rehearsal-planner.' . $session->id,
        ]);
    }

    public function show(Bands $band, RehearsalPlannerSession $session): JsonResponse
    {
        abort_unless($session->band_id === $band->id, 404);

        return response()->json([
            'session_id' => $session->id,
            'messages'   => $session->messages()->get()->map(fn ($m) => $this->formatMessage($m))->values(),
        ]);
    }

    private function formatMessage($m): array
    {
        return [
            'id'      => $m->id,
            'role'    => $m->role,
            'content' => $m->content,
            'payload' => $m->payload,
            'status'  => $m->status,
        ];
    }

    private function keyGuard(): ?JsonResponse
    {
        if (!config('services.anthropic.key')) {
            return response()->json(['error' => 'Anthropic API key not configured.'], 503);
        }
        return null;
    }
}
