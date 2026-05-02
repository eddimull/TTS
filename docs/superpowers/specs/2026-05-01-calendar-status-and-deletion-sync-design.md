# Calendar Status & Deletion Sync — Design

**Date:** 2026-05-01
**Branch:** feature/personal-gigs-mobile-api (target)
**Owner:** eddimull

## Problem

Two bugs in the Google Calendar integration:

1. **Status missing from event-calendar titles.** The booking calendar currently shows `"{Booking Name} ({Status})"`, but the event calendar (which contains the same gigs as full `Events` records once a booking is fleshed out) shows just the bare event title — readers can't tell at a glance whether a calendared gig is `draft`, `pending`, or `confirmed`.
2. **Booking deletion leaves orphans on the event/public calendars.** When a booking is deleted via the web `BookingsController@destroy`, child `Events` are removed via a mass query delete that bypasses model observers. The booking calendar entry is cleaned up (via `ProcessBookingDeleted`), but the event-calendar and public-calendar entries for child events become orphans on Google.

## Goals

- Event-calendar entries show the booking's status in the title, mirroring the booking-calendar format.
- When a booking's status changes, the linked events' calendar entries re-render with the new status without the user editing each event.
- When a booking is deleted (from any path), every Google Calendar entry it generated — booking calendar, event calendar, public calendar — is removed.

## Non-goals

- Public-calendar title formatting is not a separate concern in this spec; the public calendar inherits whatever `Events::getGoogleCalendarSummary()` returns (see Decisions).
- Legacy `BandEvents` and `Rehearsal` eventables are out of scope. Only `eventable_type === 'App\Models\Bookings'` is touched.
- The legacy `CalendarService::writeEventToCalendar()` / `writeBookingToCalendar()` methods are not invoked by the observer pipeline and are left alone.
- No changes to the API delete path (`Api\BookingsController@destroy`) — it already routes through `BookingObserver::deleting` correctly.

## Decisions

- **Title format on the event calendar:** `"{event.title} ({Booking Status})"` — uses the event's own `title` field with the parent booking's status appended, ucfirst'd. (User explicitly chose this over mirroring the booking name.)
- **Public calendar inherits the same suffix.** `Events::getGoogleCalendarSummary()` is called for both event-calendar and public-calendar writes, so the suffix appears on both. This is acceptable: the user's stated complaint was the event calendar lacked status, and the public calendar showing it is harmless / arguably desirable. Threading calendar type through the summary getter to differentiate is a bigger interface change with no asked-for benefit.
- **Cancellation already handled.** `BookingObserver::updated` already deletes child events when status flips to `cancelled`. New code skips dispatching `ProcessEventUpdated` for that transition to avoid racing the delete.

## Component changes

### A. `app/Models/Events.php` — `getGoogleCalendarSummary()`

Append the parent booking's status when the eventable is a `Bookings`.

```php
public function getGoogleCalendarSummary(): string|null
{
    if ($this->eventable_type === 'App\\Models\\Bookings' && $this->eventable) {
        return $this->title . ' (' . ucfirst($this->eventable->status) . ')';
    }
    return $this->title;
}
```

### B. `app/Jobs/ProcessBookingUpdated.php` — propagate status changes to child events

After the booking-calendar write, if `status` changed and the new status is not `cancelled`, dispatch `ProcessEventUpdated` for each child event so the event/public calendars re-render with the new title.

```php
public function handle()
{
    Log::info('ProcessBookingUpdated job started for booking ID: ' . $this->booking->id);

    $this->booking->refresh();

    $this->writeToGoogleCalendar($this->booking->band->bookingCalendar);
    $this->propagateStatusChangeToEvents();
    $this->SendNotification();
}

private function propagateStatusChangeToEvents(): void
{
    $oldStatus = $this->originalData['status'] ?? null;
    $newStatus = $this->booking->status;

    if (!$oldStatus || $oldStatus === $newStatus) {
        return;
    }

    // Cancelled bookings have their events deleted by BookingObserver::updated;
    // skip dispatch to avoid racing the delete.
    if ($newStatus === 'cancelled') {
        return;
    }

    foreach ($this->booking->events as $event) {
        ProcessEventUpdated::dispatch($event, $event->getOriginal())
            ->delay(now()->addSeconds(2));
    }
}
```

The 2-second delay matches `EventObserver::updated`'s existing pattern and lets `ShouldBeUniqueUntilProcessing` collapse duplicates if the user is also editing the event directly.

### C. `app/Http/Controllers/BookingsController.php` — fix `destroy()` orphan bug

Remove the mass-delete line. `BookingObserver::deleting` already iterates `$booking->events` and calls `$event->delete()` on each, which fires `EventObserver::deleted` → `ProcessEventDeleted` → cleans both event and public calendars.

```php
public function destroy(Bands $band, Bookings $booking)
{
    $booking->contacts()->detach();
    $booking->delete();

    return redirect()->route('Bookings Home')->with('successMessage', "{$booking->name} has been deleted.");
}
```

The `$booking->contacts()->detach()` call stays — `contacts` is a `belongsToMany`, so the `deleting` observer does not touch it.

## Data flow

### Status change (e.g. `draft` → `confirmed`)

1. User updates booking → `BookingObserver::updated` → `ProcessBookingUpdated` dispatched.
2. Job updates the **booking calendar** entry (existing behavior — title becomes `"{name} (Confirmed)"`).
3. New code: status changed and not `cancelled`, so dispatch `ProcessEventUpdated` for each linked event with a 2s delay.
4. Each `ProcessEventUpdated` writes to the event calendar (and public calendar if `additional_data->public`) — title becomes `"{event.title} (Confirmed)"`.

### Booking deletion (web)

1. User clicks delete → `BookingsController@destroy`.
2. `$booking->contacts()->detach()` removes pivot rows.
3. `$booking->delete()` → `BookingObserver::deleting` fires.
4. Observer iterates `$booking->events` and calls `$event->delete()` on each → fires `EventObserver::deleted` → `ProcessEventDeleted` → cleans event + public calendar.
5. `BookingObserver::deleted` fires → `ProcessBookingDeleted` → cleans booking calendar.

### Booking deletion (API)

No change. `Api\BookingsController@destroy` already calls `$booking->delete()` directly, which fires `BookingObserver::deleting` and cleans everything correctly.

## Error handling & edge cases

- **Booking has no events yet.** `foreach` is a no-op. Fine.
- **Booking status change to `cancelled`.** `BookingObserver::updated` calls `deleteBookingEvents()`, which iterates and calls `$event->delete()` per event → `ProcessEventDeleted` cleans calendars. The new propagate-to-events dispatch is skipped, so no race.
- **Concurrent edit on the event itself.** `ProcessEventUpdated` uses `ShouldBeUniqueUntilProcessing` keyed on `'event-updated-' . $event->id`. Duplicate dispatches collapse; last write wins, which is the desired behavior.
- **Job failures.** Existing jobs already log and rethrow. No new error paths introduced.

## Testing

PHPUnit feature tests, using existing `Bookings`/`Events` factories. Run inside the app container per `CLAUDE.md`.

1. `test_event_calendar_summary_includes_booking_status` — `Events::getGoogleCalendarSummary()` returns `"{title} (Pending)"` when the eventable is a `Bookings` with status `pending`; returns just `$title` for other eventable types.
2. `test_booking_status_change_dispatches_event_updates` — `Queue::fake()`, update a booking's status from `draft` to `pending`, assert `ProcessEventUpdated::class` is dispatched once per child event with the correct event id.
3. `test_booking_status_change_to_cancelled_does_not_dispatch_event_updates` — same setup but flip to `cancelled`; assert `ProcessEventUpdated::class` is NOT dispatched (events are being deleted by the observer instead).
4. `test_booking_destroy_triggers_event_observer_for_each_event` — `Queue::fake()`, hit the web destroy route, assert `ProcessEventDeleted::class` is dispatched once per child event AND `ProcessBookingDeleted::class` is dispatched once.

Commands:

```bash
docker-compose exec app php artisan test --filter=test_event_calendar_summary_includes_booking_status
docker-compose exec app php artisan test --filter=BookingStatusCalendarSync
```

## Files touched

- `app/Models/Events.php` — `getGoogleCalendarSummary()` modified.
- `app/Jobs/ProcessBookingUpdated.php` — `handle()` calls new private `propagateStatusChangeToEvents()`.
- `app/Http/Controllers/BookingsController.php` — `destroy()` no longer mass-deletes events.
- Tests added to existing flat `tests/Feature/` files where they fit, matching the project's existing convention:
  - Test 1 (summary format): extend `tests/Feature/EventsToGoogleCalendarTest.php`.
  - Tests 2 & 3 (status-change dispatch): extend `tests/Feature/BookingsToGoogleCalendarTest.php`.
  - Test 4 (destroy dispatches): extend `tests/Feature/BookingDeletionTest.php`.
