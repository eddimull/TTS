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

    /** Furthest forward a client may ask the calendar to reach. */
    private const MAX_FORWARD_YEARS = 6;

    /**
     * Parse a client-supplied date, accepting only strict Y-m-d.
     *
     * Carbon::parse() is deliberately avoided here: it throws on true garbage
     * (a 500) and silently rolls over garbage-adjacent input like 2026-02-31.
     *
     * @return Carbon|null null when absent or malformed — callers decide the fallback
     */
    private static function parseDateParam(?string $value): ?Carbon
    {
        if (! is_string($value) || $value === '' || ! Carbon::hasFormat($value, 'Y-m-d')) {
            return null;
        }

        $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();

        // hasFormat() validates the pattern, not the calendar: 2026-02-31 passes
        // and then silently rolls over to 2026-03-03. Require a round-trip.
        return $date->toDateString() === $value ? $date : null;
    }

    /** Cap a forward-looking bound at the horizon so clients cannot request decades. */
    private static function clampForward(Carbon $date): Carbon
    {
        $horizon = Carbon::now()->addYears(self::MAX_FORWARD_YEARS);

        return $date->greaterThan($horizon) ? $horizon : $date;
    }

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

        // Opt-in forward bound: new app versions send ?to= and lazy-fetch
        // beyond it via load-newer. An absent — or malformed — `to` preserves
        // old-client behavior (no upper bound).
        $beforeDate = self::parseDateParam($request->input('to'));

        if ($beforeDate) {
            $beforeDate = self::clampForward($beforeDate);
        }

        $events         = (new UserEventsService())->getEvents($afterDate, $beforeDate);
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
        // A missing or malformed bound is treated leniently: an empty window,
        // not an error. The calendar simply back-fetches nothing.
        $beforeDate = self::parseDateParam($request->input('before_date'));

        if (! $beforeDate) {
            return response()->json(['events' => []]);
        }

        Auth::setUser($request->user());

        // No forward clamp here: this bound walks backwards, it is not a horizon.
        $afterDate = $beforeDate->copy()->subDays(30);

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

    /**
     * Load a future window of events for the calendar's lazy forward-fetch.
     * Window: [after_date, before_date). Virtual rehearsals are generated for
     * the window by UserEventsService. There is deliberately no "reached end"
     * signal — an empty window proves nothing about later events.
     */
    public function loadNewer(Request $request): JsonResponse
    {
        $afterDate  = self::parseDateParam($request->input('after_date'));
        $beforeDate = self::parseDateParam($request->input('before_date'));

        // Missing or malformed bounds yield an empty window rather than an error.
        if (! $afterDate || ! $beforeDate) {
            return response()->json(['events' => []]);
        }

        // Forward horizon applies to the leading edge only.
        $beforeDate = self::clampForward($beforeDate);

        // An inverted (or empty) window can never contain events.
        if ($beforeDate->lessThanOrEqualTo($afterDate)) {
            return response()->json(['events' => []]);
        }

        Auth::setUser($request->user());

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
