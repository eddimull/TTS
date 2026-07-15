<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Chat\TopicUnreadService;
use App\Services\Mobile\DashboardFormatter;
use App\Services\UserEventsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /** Days of past events to include in the initial dashboard payload. */
    private const INITIAL_PAST_WINDOW_DAYS = 30;

    public function __construct(
        private readonly DashboardFormatter $formatter,
        private readonly TopicUnreadService $topicUnread,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // UserEventsService uses Auth::user() internally. Sanctum token auth does not
        // set the session guard, so we must manually bind the user to the Auth guard
        // before invoking the service. We use setUser() rather than login() to avoid
        // firing login events.
        Auth::setUser($user);

        // Mobile shows a calendar (not the web feed), so include the recent past.
        $afterDate = Carbon::now()->subDays(self::INITIAL_PAST_WINDOW_DAYS);

        $events         = (new UserEventsService())->getEvents($afterDate);
        $upcomingCharts = (new UserEventsService())->getUpcomingCharts();

        $collection = $events instanceof \Illuminate\Support\Collection
            ? $events
            : collect($events);

        $unreadByKey = $this->topicUnread->unreadCountsForConversables(
            $request->user(),
            $this->formatter->conversablePairs($collection),
        );

        $normalized = $this->formatter->formatEvents($collection, $unreadByKey);

        return response()->json([
            'events'          => $normalized,
            'upcoming_charts' => $upcomingCharts instanceof \Illuminate\Support\Collection
                ? $upcomingCharts->values()
                : collect($upcomingCharts)->values(),
        ]);
    }

    /**
     * Load an older 30-day window of events for the calendar's lazy back-fetch.
     * Mirrors the web DashboardController::loadOlderEvents pattern.
     */
    public function loadOlder(Request $request): JsonResponse
    {
        $beforeDateInput = $request->input('before_date');

        if (! $beforeDateInput) {
            return response()->json(['events' => []]);
        }

        Auth::setUser($request->user());

        $beforeDate = Carbon::parse($beforeDateInput);
        $afterDate  = $beforeDate->copy()->subDays(30);

        $events = (new UserEventsService())->getEvents($afterDate, $beforeDate);

        $collection = $events instanceof \Illuminate\Support\Collection
            ? $events
            : collect($events);

        $unreadByKey = $this->topicUnread->unreadCountsForConversables(
            $request->user(),
            $this->formatter->conversablePairs($collection),
        );

        return response()->json([
            'events' => $this->formatter->formatEvents($collection, $unreadByKey),
        ]);
    }
}
