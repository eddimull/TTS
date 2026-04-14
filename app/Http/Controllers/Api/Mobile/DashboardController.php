<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Mobile\DashboardFormatter;
use App\Services\UserEventsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardFormatter $formatter) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // UserEventsService uses Auth::user() internally. Sanctum token auth does not
        // set the session guard, so we must manually bind the user to the Auth guard
        // before invoking the service. We use setUser() rather than login() to avoid
        // firing login events.
        Auth::setUser($user);

        $events         = (new UserEventsService())->getEvents();
        $upcomingCharts = (new UserEventsService())->getUpcomingCharts();

        $collection = $events instanceof \Illuminate\Support\Collection
            ? $events
            : collect($events);

        $normalized = $collection->map(fn ($e) => $this->formatter->normalizeEvent($e))->values();

        return response()->json([
            'events'          => $normalized,
            'upcoming_charts' => $upcomingCharts instanceof \Illuminate\Support\Collection
                ? $upcomingCharts->values()
                : collect($upcomingCharts)->values(),
        ]);
    }
}
