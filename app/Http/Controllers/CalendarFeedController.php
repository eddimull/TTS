<?php

namespace App\Http\Controllers;

use App\Models\Events;
use App\Models\User;
use App\Services\UserEventsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event as CalendarEvent;
use Symfony\Component\HttpFoundation\Response;

class CalendarFeedController extends Controller
{
    /**
     * How far back the feed reaches. Subscriptions want some recent history so
     * a just-passed gig doesn't vanish, but there's no reason to ship years of
     * old events to every calendar client.
     */
    private const PAST_WINDOW_DAYS = 30;

    /**
     * How far forward the feed reaches. Twelve months covers planning horizons
     * for bookings/rehearsals without unbounded growth.
     */
    private const FUTURE_WINDOW_MONTHS = 12;

    /**
     * Serve a user's personal ICS calendar feed.
     *
     * This endpoint is intentionally unauthenticated — Google/Apple Calendar
     * fetch it on a schedule with no session — so the long, random
     * calendar_token in the URL is the credential. The set of events is the
     * SAME set the user sees on their dashboard: we reuse UserEventsService so
     * the feed can never diverge from the app (members see all band events,
     * sub-only users see only what they're assigned).
     *
     * Only member-appropriate fields are emitted (title, time, venue, a short
     * description). Financial fields (price, deposits, contracts) are never
     * included.
     */
    public function show(string $token): Response
    {
        // Strip a trailing ".ics" so both /calendar/{token} and
        // /calendar/{token}.ics resolve to the same user.
        $token = preg_replace('/\.ics$/', '', $token);

        $user = User::where('calendar_token', $token)->first();

        abort_if($user === null, 404);

        // UserEventsService reads Auth::user() internally; Sanctum/session are
        // absent here, so bind the resolved user to the guard exactly like the
        // mobile DashboardController does. setUser() avoids firing login events.
        Auth::setUser($user);

        $after  = Carbon::now()->subDays(self::PAST_WINDOW_DAYS);
        $before = Carbon::now()->addMonths(self::FUTURE_WINDOW_MONTHS);

        // getEventIds() applies the full entitlement rules and naturally
        // excludes virtual rehearsals (which have no real event id and thus no
        // stable UID for a subscription).
        $eventIds = (new UserEventsService())->getEventIds($after, $before);

        $events = empty($eventIds)
            ? collect()
            : Events::whereIn('id', $eventIds)
                ->with('eventable.band')
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();

        $calendar = Calendar::create($this->calendarName($user))
            // Hint to clients how often to re-poll the feed.
            ->refreshInterval(60 * 6);

        foreach ($events as $event) {
            $calendarEvent = $this->buildEvent($event);
            if ($calendarEvent !== null) {
                $calendar->event($calendarEvent);
            }
        }

        return response($calendar->get(), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="thatstheticket.ics"',
            // The feed is per-user and changes as events change; don't let
            // proxies cache it across users or for long.
            'Cache-Control'       => 'private, max-age=300',
        ]);
    }

    private function calendarName(User $user): string
    {
        $appName = config('app.name', 'TTS');

        return trim($user->name) !== ''
            ? "{$user->name} — {$appName}"
            : $appName;
    }

    /**
     * Map a single Events model to an iCalendar VEVENT, or null if it lacks the
     * minimum data to render. Reuses the model's existing Google Calendar
     * helpers so the feed stays consistent with the server-side group sync.
     */
    private function buildEvent(Events $event): ?CalendarEvent
    {
        if ($event->date === null) {
            return null;
        }

        // startDateTime/endDateTime are naive wall-clock strings; interpret them
        // in the event's venue timezone when set (for out-of-timezone gigs),
        // falling back to the app timezone.
        $timezone = !empty($event->venue_timezone)
            ? $event->venue_timezone
            : config('app.timezone');

        $start = Carbon::parse($event->startDateTime, $timezone);
        $end   = Carbon::parse($event->endDateTime, $timezone);

        $calendarEvent = CalendarEvent::create()
            ->uniqueIdentifier('event-' . $event->id . '@thatstheticket')
            ->name($event->getGoogleCalendarSummary() ?? ($event->title ?: 'Event'));

        // Events with no explicit start time use a noon placeholder in
        // startDateTime; treat those as all-day so calendars don't show a
        // misleading 12:00 slot.
        if ($event->start_time === null) {
            $calendarEvent->startsAt($start, withTime: false)->fullDay();
        } else {
            $calendarEvent->startsAt($start)->endsAt($end);
        }

        $description = $event->getGoogleCalendarDescription();
        if (!empty($description)) {
            $calendarEvent->description($description);
        }

        // Venue fields live on the event row for booking-derived events, but
        // fall back to the polymorphic eventable for rehearsals / legacy rows
        // that weren't backfilled (mirrors CalendarEventFormatter).
        $venueName    = $event->venue_name ?? ($event->eventable?->venue_name ?? null);
        $venueAddress = $event->venue_address ?? ($event->eventable?->venue_address ?? null);

        if (!empty($venueName)) {
            $calendarEvent->address(
                $venueAddress ? $venueName . ', ' . $venueAddress : $venueName,
                $venueName,
            );
        }

        return $calendarEvent;
    }
}
