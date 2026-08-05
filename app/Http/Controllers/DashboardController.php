<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use App\Services\MileageService;
use App\Services\UserEventsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $events = (new UserEventsService())->getEvents();
        $events = $this->attachLodgingSummaries($events);
        $upcomingCharts = (new UserEventsService())->getUpcomingCharts();


        // $stats = (new MileageService())->handle($events);
        // dd($stats);
        return Inertia::render('Dashboard',
        [
            'events'=>$events,
            'upcomingCharts'=>$upcomingCharts,
            'stats'=>[]
            ]);
        }

    /**
     * Load older events for infinite scroll
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadOlderEvents(Request $request)
    {
        $beforeDate = $request->input('before_date');

        if (!$beforeDate) {
            return response()->json(['events' => []]);
        }

        $beforeDate = Carbon::parse($beforeDate);

        // Load events before the given date, going back 30 days at a time
        $afterDate = $beforeDate->copy()->subDays(30);

        $events = (new UserEventsService())->getEvents($afterDate, $beforeDate);
        $events = $this->attachLodgingSummaries($events);

        return response()->json(['events' => $events]);
    }

    /**
     * Attach a logistics-only lodging summary to each event. Uses the
     * shared formatter so dashboard payloads can never leak confirmation
     * numbers or notes.
     *
     * UserEventsService::getEvents() does NOT return a uniform collection of
     * Events models: owner/member events start as models but get converted
     * to plain arrays partway through (merged with virtual rehearsals, which
     * have no backing model), while sub-only users' events (getSubEvents())
     * stay as Eloquent models throughout. So each item in the collection can
     * be either an array or a model, and this must branch per-item exactly
     * like the rest of UserEventsService already does (see its own
     * is_array($event) checks).
     *
     * Mutating array items in a plain foreach is a no-op (PHP foreach copies
     * array values, unlike objects which are handle-like) — so, like
     * UserEventsService itself, this rebuilds and returns the collection via
     * map() rather than mutating in place. Callers MUST reassign the result.
     */
    private function attachLodgingSummaries($events)
    {
        $service = app(\App\Services\Mobile\LodgingService::class);
        $ids = collect($events)
            ->map(fn ($event) => is_array($event) ? ($event['id'] ?? null) : ($event->id ?? null))
            ->filter()
            ->all();
        $byEvent = \App\Models\Lodging::whereIn('event_id', $ids)
            ->withCount('rooms')
            ->orderBy('check_in_at')
            ->get()
            ->groupBy('event_id');

        return collect($events)->map(function ($event) use ($byEvent, $service) {
            $id = is_array($event) ? ($event['id'] ?? null) : ($event->id ?? null);
            $summary = ($byEvent->get($id) ?? collect())
                ->map(fn ($l) => $service->formatLogistics($l))->values()->toArray();

            if (is_array($event)) {
                $event['lodgings_summary'] = $summary;
            } else {
                $event->lodgings_summary = $summary;
            }

            return $event;
        })->values();
    }
}
