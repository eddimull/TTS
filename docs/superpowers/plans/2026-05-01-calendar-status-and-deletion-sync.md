# Calendar Status & Deletion Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Google Calendar event-calendar titles reflect parent booking status, propagate booking status changes to child events on the calendar, and stop the web booking-destroy path from leaking orphan calendar entries.

**Architecture:** Three small, surgical edits — one to `Events::getGoogleCalendarSummary()`, one to `ProcessBookingUpdated::handle()`, and one to `BookingsController::destroy()`. The existing observer/job pipeline (`BookingObserver` → `ProcessBooking{Created,Updated,Deleted}`, `EventObserver` → `ProcessEvent{Created,Updated,Deleted}`) already does the heavy lifting; we're plugging gaps in it.

**Tech Stack:** Laravel 12, PHPUnit 11, Mockery, existing `GoogleCalendarService` mock pattern (see `tests/Feature/BookingDeletionTest.php` and `tests/Feature/BookingsToGoogleCalendarTest.php`). All commands run inside the app container per `CLAUDE.md`.

**Spec:** `docs/superpowers/specs/2026-05-01-calendar-status-and-deletion-sync-design.md`

---

## File Map

| File | Disposition | Responsibility |
|---|---|---|
| `app/Models/Events.php` | Modify (`getGoogleCalendarSummary()`) | Title for event/public calendar entries; appends booking status when parent is a Booking |
| `app/Jobs/ProcessBookingUpdated.php` | Modify (`handle()`, add private method) | When status changes (and not to cancelled), fan out `ProcessEventUpdated` for each child event |
| `app/Http/Controllers/BookingsController.php` | Modify (`destroy()`) | Stop bypassing model events on child-event deletion |
| `tests/Feature/EventsToGoogleCalendarTest.php` | Modify (add 2 tests) | Cover the booking-status suffix + non-Bookings fallback |
| `tests/Feature/BookingsToGoogleCalendarTest.php` | Modify (add 2 tests) | Cover the status-change-fan-out (positive + cancelled-skip) |
| `tests/Feature/BookingDeletionTest.php` | Modify (add 1 test) | Cover the web destroy route specifically |

---

## Task 1: Test event-calendar summary appends booking status

**Files:**
- Test: `tests/Feature/EventsToGoogleCalendarTest.php`

This test will fail because `Events::getGoogleCalendarSummary()` currently returns just `$this->title`.

- [ ] **Step 1: Write the failing test**

Open `tests/Feature/EventsToGoogleCalendarTest.php` and add this method just below the existing `test_returns_google_calendar_summary` (around line 74):

```php
public function test_summary_appends_booking_status_when_eventable_is_booking(): void
{
    // The factory already creates an Events with a Bookings as eventable.
    $this->event->title = 'Test Event';
    $this->event->eventable->status = 'pending';
    $this->event->eventable->save();

    $summary = $this->event->getGoogleCalendarSummary();

    $this->assertEquals('Test Event (Pending)', $summary);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
docker-compose exec app php artisan test --filter=test_summary_appends_booking_status_when_eventable_is_booking
```
Expected: FAIL — `Failed asserting that 'Test Event' equals expected 'Test Event (Pending)'`.

- [ ] **Step 3: Implement summary change**

Edit `app/Models/Events.php`. Replace the existing method (currently around line 256):

```php
public function getGoogleCalendarSummary(): string|null
{
    if ($this->eventable_type === 'App\\Models\\Bookings' && $this->eventable) {
        return $this->title . ' (' . ucfirst($this->eventable->status) . ')';
    }
    return $this->title;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
docker-compose exec app php artisan test --filter=test_summary_appends_booking_status_when_eventable_is_booking
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/EventsToGoogleCalendarTest.php app/Models/Events.php
git commit -m "$(cat <<'EOF'
feat(calendar): show booking status in event-calendar titles

Event-calendar entries derived from a Booking now display
"{Event Title} ({Status})", mirroring the booking-calendar format
so readers can tell at a glance whether a calendared gig is draft,
pending, or confirmed.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Test summary falls back to title for non-Booking eventables

**Files:**
- Test: `tests/Feature/EventsToGoogleCalendarTest.php`

Guards against accidentally regressing the legacy `BandEvents` (or future eventable types) path.

- [ ] **Step 1: Write the test**

Add below the test from Task 1:

```php
public function test_summary_returns_bare_title_when_eventable_is_not_a_booking(): void
{
    $this->event->title = 'Test Event';
    $this->event->eventable_type = 'App\\Models\\BandEvents';
    // Don't reload eventable; the type check alone should short-circuit.

    $summary = $this->event->getGoogleCalendarSummary();

    $this->assertEquals('Test Event', $summary);
}
```

- [ ] **Step 2: Run test to verify it passes**

Run:
```bash
docker-compose exec app php artisan test --filter=test_summary_returns_bare_title_when_eventable_is_not_a_booking
```
Expected: PASS — the implementation from Task 1 already handles this correctly.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/EventsToGoogleCalendarTest.php
git commit -m "$(cat <<'EOF'
test(calendar): assert summary stays bare for non-Booking eventables

Pins down the fallback behavior so the booking-status suffix doesn't
accidentally leak onto BandEvents or future eventable types.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Test ProcessBookingUpdated fans out event updates on status change

**Files:**
- Test: `tests/Feature/BookingsToGoogleCalendarTest.php`

We test the job directly rather than going through `Queue::fake()` at the controller level, because the new fan-out logic lives inside `ProcessBookingUpdated::handle()`.

- [ ] **Step 1: Write the failing test**

Open `tests/Feature/BookingsToGoogleCalendarTest.php` and add these `use` statements at the top (just below the existing `use` block):

```php
use App\Jobs\ProcessBookingUpdated;
use App\Jobs\ProcessEventUpdated;
use App\Models\Events;
use App\Models\EventTypes;
use Illuminate\Support\Facades\Queue;
```

Then add this test method to the class (place it after `test_deletes_from_google_calendar`, around line 140):

```php
public function test_status_change_dispatches_event_update_for_each_child_event(): void
{
    BandCalendars::factory()->create([
        'band_id' => $this->booking->band->id,
        'type' => 'booking',
    ]);

    $eventType = EventTypes::factory()->create();
    $event1 = Events::withoutEvents(fn () => Events::factory()->create([
        'eventable_id' => $this->booking->id,
        'eventable_type' => Bookings::class,
        'event_type_id' => $eventType->id,
    ]));
    $event2 = Events::withoutEvents(fn () => Events::factory()->create([
        'eventable_id' => $this->booking->id,
        'eventable_type' => Bookings::class,
        'event_type_id' => $eventType->id,
    ]));

    // Mock the calendar write so the job's first step is a no-op.
    $mockService = $this->mock(GoogleCalendarService::class);
    $mockService->shouldReceive('insertEvent')->andReturn(new GoogleEvent());

    $this->booking->status = 'pending';
    $this->booking->save();

    Queue::fake();

    $job = new ProcessBookingUpdated($this->booking, ['status' => 'draft']);
    $job->handle();

    Queue::assertPushed(ProcessEventUpdated::class, 2);
    Queue::assertPushed(ProcessEventUpdated::class, fn ($job) =>
        $this->getProtectedProperty($job, 'event')->id === $event1->id
    );
    Queue::assertPushed(ProcessEventUpdated::class, fn ($job) =>
        $this->getProtectedProperty($job, 'event')->id === $event2->id
    );
}

private function getProtectedProperty(object $object, string $property)
{
    $reflection = new \ReflectionClass($object);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);
    return $prop->getValue($object);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
docker-compose exec app php artisan test --filter=test_status_change_dispatches_event_update_for_each_child_event
```
Expected: FAIL — `ProcessEventUpdated` is dispatched 0 times, expected 2.

- [ ] **Step 3: Implement fan-out in ProcessBookingUpdated**

Edit `app/Jobs/ProcessBookingUpdated.php`. Replace the existing `handle()` method with this version, and add the new private method below it:

```php
public function handle()
{
    Log::info('ProcessBookingUpdated job started for booking ID: ' . $this->booking->id);

    $this->booking->refresh();
    Log::debug('Refreshed booking from database');

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

    // Cancelled bookings have their child events deleted by BookingObserver::updated;
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

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
docker-compose exec app php artisan test --filter=test_status_change_dispatches_event_update_for_each_child_event
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/BookingsToGoogleCalendarTest.php app/Jobs/ProcessBookingUpdated.php
git commit -m "$(cat <<'EOF'
feat(calendar): propagate booking status change to child events

When a booking's status changes (and the new status is not
cancelled), dispatch ProcessEventUpdated for each child event so the
event/public calendar entries re-render with the new status in their
title without the user having to edit each event individually.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Test cancellation does NOT dispatch event updates

**Files:**
- Test: `tests/Feature/BookingsToGoogleCalendarTest.php`

Cancellation is handled separately (via `BookingObserver::updated` calling `deleteBookingEvents`). Dispatching `ProcessEventUpdated` would race the delete.

- [ ] **Step 1: Write the test**

Add this test method right after the one from Task 3:

```php
public function test_status_change_to_cancelled_does_not_dispatch_event_updates(): void
{
    BandCalendars::factory()->create([
        'band_id' => $this->booking->band->id,
        'type' => 'booking',
    ]);

    $eventType = EventTypes::factory()->create();
    Events::withoutEvents(fn () => Events::factory()->create([
        'eventable_id' => $this->booking->id,
        'eventable_type' => Bookings::class,
        'event_type_id' => $eventType->id,
    ]));

    $mockService = $this->mock(GoogleCalendarService::class);
    $mockService->shouldReceive('insertEvent')->andReturn(new GoogleEvent());

    $this->booking->status = 'cancelled';
    $this->booking->save();

    Queue::fake();

    $job = new ProcessBookingUpdated($this->booking, ['status' => 'pending']);
    $job->handle();

    Queue::assertNotPushed(ProcessEventUpdated::class);
}
```

- [ ] **Step 2: Run test to verify it passes**

Run:
```bash
docker-compose exec app php artisan test --filter=test_status_change_to_cancelled_does_not_dispatch_event_updates
```
Expected: PASS — the implementation from Task 3 already includes the cancelled-skip guard.

- [ ] **Step 3: Run both new BookingsToGoogleCalendar tests together**

Run:
```bash
docker-compose exec app php artisan test --filter=BookingsToGoogleCalendar
```
Expected: PASS for all (existing + new) tests in this file.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/BookingsToGoogleCalendarTest.php
git commit -m "$(cat <<'EOF'
test(calendar): assert cancellation skips event-update fan-out

Pins down the cancellation-path guard so future refactors don't
accidentally re-introduce a race between ProcessEventUpdated and the
event deletes that BookingObserver::updated triggers when a booking
is cancelled.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Test web destroy dispatches deletion for each child event

**Files:**
- Test: `tests/Feature/BookingDeletionTest.php`

The existing `test_booking_deletion_removes_associated_events_and_calendar_entries` calls `$booking->delete()` directly, which goes through `BookingObserver::deleting` and is already correct. The bug we're fixing is in the **web controller** path, which currently does `$booking->events()->delete()` first (mass query — bypasses observers). We need a test that hits the route.

- [ ] **Step 1: Write the failing test**

Open `tests/Feature/BookingDeletionTest.php` and add this `use` statement at the top (alongside the existing imports):

```php
use App\Jobs\ProcessBookingDeleted;
use App\Jobs\ProcessEventDeleted;
use Illuminate\Support\Facades\Queue;
```

Then add this test method to the class (place it after `test_booking_deletion_with_no_events`, around line 224):

```php
public function test_web_destroy_dispatches_event_deletion_for_each_child_event(): void
{
    $booking = Bookings::withoutEvents(function () {
        return Bookings::factory()->create([
            'band_id' => $this->band->id,
            'date' => now()->addDays(10),
            'event_type_id' => $this->eventType->id,
        ]);
    });

    $event1 = Events::withoutEvents(fn () => Events::factory()->create([
        'eventable_id' => $booking->id,
        'eventable_type' => Bookings::class,
        'event_type_id' => $this->eventType->id,
        'date' => $booking->date,
        'title' => 'Event 1',
    ]));

    $event2 = Events::withoutEvents(fn () => Events::factory()->create([
        'eventable_id' => $booking->id,
        'eventable_type' => Bookings::class,
        'event_type_id' => $this->eventType->id,
        'date' => $booking->date,
        'title' => 'Event 2',
    ]));

    Queue::fake();

    $response = $this->delete(route('bands.booking.destroy', [
        'band' => $this->band,
        'booking' => $booking,
    ]));

    $response->assertRedirect(route('Bookings Home'));

    Queue::assertPushed(ProcessEventDeleted::class, 2);
    Queue::assertPushed(ProcessEventDeleted::class, fn ($job) =>
        $this->getProtectedProperty($job, 'event')->id === $event1->id
    );
    Queue::assertPushed(ProcessEventDeleted::class, fn ($job) =>
        $this->getProtectedProperty($job, 'event')->id === $event2->id
    );
    Queue::assertPushed(ProcessBookingDeleted::class, 1);
}

private function getProtectedProperty(object $object, string $property)
{
    $reflection = new \ReflectionClass($object);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);
    return $prop->getValue($object);
}
```

Note: the existing setUp in this test class calls `$this->actingAs($this->user)` and creates the band as the user. We need to make sure the user has permission to delete bookings on this band. Check whether the existing `test_booking_deletion_*` tests pass through the controller — they don't, they call `$booking->delete()` directly. To ensure the destroy route authorizes our user, we need the user to be a band owner.

- [ ] **Step 2: Add user as band owner in setUp**

Look at the existing `setUp()` method in `tests/Feature/BookingDeletionTest.php`. After `$this->band = Bands::factory()->create();`, add this line so the destroy route's authorization passes:

```php
$this->band->owners()->create(['user_id' => $this->user->id]);
```

If `Bands::factory()` already supports `withOwners()` (it does, per `BookingsFactory`), an alternative is to recreate the band: `$this->band = Bands::factory()->withOwners()->create();` and then `$this->user = $this->band->owners->first()->user;`. **Use the simpler `owners()->create([...])` approach** to avoid disturbing the rest of setUp.

If band-permission middleware additionally requires `user_permissions` for write access on bookings, watch for a 403 in step 3 and add:

```php
\App\Models\UserPermissions::create([
    'user_id' => $this->user->id,
    'band_id' => $this->band->id,
    'permission' => 'bookings',
    'access' => 'write',
]);
```

(Only add this if step 3 returns 403.)

- [ ] **Step 3: Run test to verify it fails**

Run:
```bash
docker-compose exec app php artisan test --filter=test_web_destroy_dispatches_event_deletion_for_each_child_event
```
Expected: FAIL — `ProcessEventDeleted` is dispatched 0 times, expected 2 (because the controller's mass `events()->delete()` bypasses `EventObserver::deleted`).

If you instead see a 403/redirect-to-login or "route not found" failure, fix the auth setup per step 2 first, then re-run. The expected real failure is the assertion mismatch on dispatch counts.

- [ ] **Step 4: Fix the controller**

Edit `app/Http/Controllers/BookingsController.php`. Replace the existing `destroy()` method (around line 414):

```php
public function destroy(Bands $band, Bookings $booking)
{
    $booking->contacts()->detach();
    $booking->delete();

    return redirect()->route('Bookings Home')->with('successMessage', "{$booking->name} has been deleted.");
}
```

The single removed line is `$booking->events()->delete();`. The `BookingObserver::deleting` hook now handles event removal via `$event->delete()` per event, which fires `EventObserver::deleted` → dispatches `ProcessEventDeleted`.

- [ ] **Step 5: Run test to verify it passes**

Run:
```bash
docker-compose exec app php artisan test --filter=test_web_destroy_dispatches_event_deletion_for_each_child_event
```
Expected: PASS.

- [ ] **Step 6: Run the entire BookingDeletionTest class to confirm no regressions**

Run:
```bash
docker-compose exec app php artisan test --filter=BookingDeletionTest
```
Expected: All four tests pass (the three originals + the new one).

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/BookingDeletionTest.php app/Http/Controllers/BookingsController.php
git commit -m "$(cat <<'EOF'
fix(calendar): stop orphaning event/public calendar entries on booking delete

BookingsController@destroy was mass-deleting child events via the
query builder, which bypasses Eloquent model events. As a result,
EventObserver::deleted never fired, ProcessEventDeleted never ran,
and event-calendar / public-calendar entries became orphans on
Google. BookingObserver::deleting already iterates child events and
calls \$event->delete() per event, which fires the observer
correctly — so the controller's mass delete was redundant AND
buggy. Removing it.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Run the affected test files together to confirm no regressions

- [ ] **Step 1: Run all three modified test files**

Run:
```bash
docker-compose exec app php artisan test \
  tests/Feature/EventsToGoogleCalendarTest.php \
  tests/Feature/BookingsToGoogleCalendarTest.php \
  tests/Feature/BookingDeletionTest.php
```
Expected: ALL tests pass.

- [ ] **Step 2: Run any tests that reference `getGoogleCalendarSummary` to catch indirect breakage**

Run:
```bash
docker-compose exec app php artisan test --filter=GoogleCalendar
```
Expected: ALL tests pass. If any unrelated test fails because it asserts an exact event-calendar title without the status suffix, update its expectation to match the new format `"{title} ({Status})"` — that is the new contract.

- [ ] **Step 3: Run the entire test suite (parallel) once everything looks green**

Run:
```bash
docker-compose exec app php artisan test --parallel --processes=4
```
Expected: ALL tests pass.

If anything fails that you didn't write a fix for, treat it as in-scope for this plan: investigate, fix, and add a follow-up commit (don't skip or mark the task complete).

- [ ] **Step 4: Final verification commit (only if you made fixes in step 3)**

If step 3 surfaced unrelated breakage you fixed, commit it:

```bash
git add <files>
git commit -m "$(cat <<'EOF'
test(calendar): update expectations for new event-summary format

Adjust assertions that pinned the old bare-title event-calendar
summary to the new "{title} ({Status})" format introduced by the
booking-status sync work.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

If step 3 was already green, skip this step.
