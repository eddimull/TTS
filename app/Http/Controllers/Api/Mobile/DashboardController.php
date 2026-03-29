<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\UserEventsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // UserEventsService uses Auth::user() internally. Sanctum token auth does not
        // set the session guard, so we must manually bind the user to the Auth guard
        // before invoking the service. We use setUser() rather than login() to avoid
        // firing login events.
        Auth::setUser($user);

        $events = (new UserEventsService())->getEvents();
        $upcomingCharts = (new UserEventsService())->getUpcomingCharts();

        return response()->json([
            'events' => $events instanceof \Illuminate\Support\Collection
                ? $events->map(fn($e) => is_object($e) && method_exists($e, 'toArray') ? $e->toArray() : (array) $e)->values()
                : (array) $events,
            'upcoming_charts' => $upcomingCharts instanceof \Illuminate\Support\Collection
                ? $upcomingCharts->values()
                : (array) $upcomingCharts,
        ]);
    }
}
