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

        $collection = $events instanceof \Illuminate\Support\Collection
            ? $events
            : collect($events);

        $normalized = $collection->map(function ($e) {
            $e = is_object($e) && method_exists($e, 'toArray') ? $e->toArray() : (array) $e;

            $source = $e['event_source'] ?? match (true) {
                str_contains($e['eventable_type'] ?? '', 'Rehearsal') => 'rehearsal',
                str_contains($e['eventable_type'] ?? '', 'Booking') => 'booking',
                default => 'band_event',
            };

            $date = $e['date'] ?? null;
            if ($date && !is_string($date)) {
                $date = is_array($date) ? ($date['date'] ?? null) : (string) $date;
            }
            // Strip time component if present (e.g. "2026-04-15 00:00:00")
            if ($date && strlen($date) > 10) {
                $date = substr($date, 0, 10);
            }

            $time = $e['time'] ?? null;
            if ($time && !is_string($time)) {
                $time = is_array($time) ? ($time['time'] ?? null) : (string) $time;
            }
            if ($time && strlen($time) > 5) {
                $time = substr($time, 0, 5);
            }

            return [
                'id'              => $e['id'] ?? null,
                'key'             => $e['key'] ?? null,
                'title'           => $e['title'] ?? $e['booking_name'] ?? 'Untitled',
                'date'            => $date,
                'time'            => $time,
                'event_type'      => $e['event_type_name'] ?? null,
                'event_source'    => $source,
                'venue_name'      => $e['venue_name'] ?? null,
                'venue_address'   => $e['venue_address'] ?? null,
                'status'          => $e['status'] ?? null,
                'live_session_id' => $e['live_session_id'] ?? null,
            ];
        })->values();

        return response()->json([
            'events'          => $normalized,
            'upcoming_charts' => $upcomingCharts instanceof \Illuminate\Support\Collection
                ? $upcomingCharts->values()
                : collect($upcomingCharts)->values(),
        ]);
    }
}
