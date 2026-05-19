# Calendar Job Crash Guards — Implementation Plan (PR A)

> **For agentic workers:** Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Stop two recurring production crashes in the calendar-sync queue jobs (Sentry TTS-BAND-2J and TTS-BAND-11E) without altering correct behavior.

**Architecture:** Two independent, defensive fixes. (1) `ProcessBookingCreated` dereferences the result of `writeToGoogleCalendar()` without the null-guard its sibling `ProcessEventCreated` already has — add the guard. (2) `Events.additional_data` is cast `object`, but double-encoded rows decode to a *string*; `ProcessEventCreated` then does `additional_data->public` and fatals. Fix the read side once with a model accessor that normalizes any storage shape to an object.

**Scope:** PR A only — crash guards. The separate data-corruption repair (factory fix + migration to un-double-encode existing rows) is a deliberate follow-up (PR B), NOT in this plan.

**Tech Stack:** Laravel 12, PHPUnit 11.

---

## Task 1: Guard `ProcessBookingCreated` against a missing booking calendar

**Files:**
- Modify: `app/Jobs/ProcessBookingCreated.php`
- Test: `tests/Feature/ProcessBookingCreatedTest.php` (create)

**Background:** `ProcessBookingCreated::handle()` does:
```php
$event = $this->booking->writeToGoogleCalendar($this->booking->band->bookingCalendar);
Log::info('Created Google Calendar event with ID: ' . $event->id);
$this->booking->storeGoogleEventId($this->booking->band->bookingCalendar, $event->id);
```
`band->bookingCalendar` is a `hasOne` returning `null` when the band has no `type='booking'` calendar. `writeToGoogleCalendar(null)` returns `false` (see `GoogleCalendarWritable` trait). Then `$event->id` fatals: "Attempt to read property id on false". `ProcessEventCreated` already does this correctly with `if ($event) { ... }`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProcessBookingCreatedTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\ProcessBookingCreated;
use App\Models\Bands;
use App\Models\Bookings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessBookingCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_does_not_throw_when_band_has_no_booking_calendar(): void
    {
        // A band with no booking calendar -> writeToGoogleCalendar() returns false.
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        $this->assertNull($band->bookingCalendar);

        // Must not throw "Attempt to read property id on false".
        (new ProcessBookingCreated($booking))->handle();

        // Nothing was synced.
        $this->assertDatabaseMissing('google_events', [
            'google_eventable_id'   => $booking->id,
            'google_eventable_type' => Bookings::class,
        ]);
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `docker-compose exec -T app php artisan test --filter=test_handle_does_not_throw_when_band_has_no_booking_calendar`
Expected: FAIL — `Attempt to read property "id" on false` (or `bool`).

If it instead fails on the `assertNull` (band somehow has a calendar) or a factory error, fix the test setup first — the `Bands` factory must not auto-create a booking calendar. If it does, inspect `database/factories/BandsFactory.php` and create the booking with a band that genuinely has no `type='booking'` calendar row.

- [ ] **Step 3: Add the guard**

In `app/Jobs/ProcessBookingCreated.php`, replace the body of the `try` block. Current:

```php
        try {
            $event = $this->booking->writeToGoogleCalendar($this->booking->band->bookingCalendar);
            Log::info('Created Google Calendar event with ID: ' . $event->id);
            $this->booking->storeGoogleEventId($this->booking->band->bookingCalendar, $event->id);
            Log::info('Created Google Events record for booking ID: ' . $this->booking->id);
        } catch (\Exception $e) {
            Log::error('Failed to update booking in calendar: ' . $e->getMessage());
        }
```

Replace with:

```php
        try {
            $bookingCalendar = $this->booking->band->bookingCalendar;

            if (!$bookingCalendar) {
                Log::warning('Skipping calendar sync: band has no booking calendar', [
                    'booking_id' => $this->booking->id,
                    'band_id'    => $this->booking->band_id,
                ]);
                return;
            }

            $event = $this->booking->writeToGoogleCalendar($bookingCalendar);

            if (!$event) {
                Log::warning('Skipping calendar sync: writeToGoogleCalendar returned no event', [
                    'booking_id' => $this->booking->id,
                ]);
                return;
            }

            Log::info('Created Google Calendar event with ID: ' . $event->id);
            $this->booking->storeGoogleEventId($bookingCalendar, $event->id);
            Log::info('Created Google Events record for booking ID: ' . $this->booking->id);
        } catch (\Exception $e) {
            Log::error('Failed to update booking in calendar: ' . $e->getMessage());
        }
```

This also fixes a latent bug: the original called `band->bookingCalendar` twice (a second query); now it is fetched once.

- [ ] **Step 4: Run the test, confirm it passes**

Run: `docker-compose exec -T app php artisan test --filter=test_handle_does_not_throw_when_band_has_no_booking_calendar`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/ProcessBookingCreated.php tests/Feature/ProcessBookingCreatedTest.php
git commit -m "fix: guard ProcessBookingCreated against missing booking calendar

writeToGoogleCalendar() returns false when a band has no booking
calendar; the job then read ->id on false and fataled.

Fixes TTS-BAND-2J"
```

---

## Task 2: Normalize `Events.additional_data` so a string-typed value never crashes readers

**Files:**
- Modify: `app/Models/Events.php`
- Test: `tests/Unit/Models/EventsAdditionalDataTest.php` (create)

**Background:** `additional_data` is cast `'object'`. Some rows store double-encoded JSON (a JSON string whose content is itself JSON). The `object` cast decodes once → a PHP `string`, not `stdClass`. `ProcessEventCreated::handle()` then does `$this->event->additional_data->public` → fatal "Attempt to read property public on string". Confirmed in the DB: `JSON_TYPE(additional_data)` is `STRING` for affected rows, `OBJECT` for healthy ones. `Events.php` also reads `additional_data->times` elsewhere, so the fix must cover all readers.

Fix: replace the `'object'` cast with an accessor that always returns an object (or null), decoding a second time if the cast produced a string. This is purely a read-side normalization — it does NOT rewrite stored data (that is PR B).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/EventsAdditionalDataTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Bookings;
use App\Models\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventsAdditionalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_additional_data_returns_object_for_normal_json(): void
    {
        $event = Events::factory()->create();
        // Write a clean JSON object directly.
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => json_encode(['public' => true])]);

        $fresh = Events::find($event->id);
        $this->assertIsObject($fresh->additional_data);
        $this->assertTrue($fresh->additional_data->public);
    }

    public function test_additional_data_returns_object_for_double_encoded_json(): void
    {
        $event = Events::factory()->create();
        // Simulate a double-encoded row: the column holds a JSON *string*
        // whose decoded content is itself JSON. json_encode twice.
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => json_encode(json_encode(['public' => true]))]);

        $fresh = Events::find($event->id);
        $this->assertIsObject($fresh->additional_data);
        $this->assertTrue($fresh->additional_data->public);
    }

    public function test_additional_data_is_null_when_absent(): void
    {
        $event = Events::factory()->create();
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => null]);

        $fresh = Events::find($event->id);
        $this->assertNull($fresh->additional_data);
    }
}
```

- [ ] **Step 2: Run the tests, confirm the double-encoded one fails**

Run: `docker-compose exec -T app php artisan test --filter=EventsAdditionalDataTest`
Expected: `test_additional_data_returns_object_for_double_encoded_json` FAILS — the `object` cast returns a string, so `assertIsObject` fails / `->public` would error. The other two may pass already.

- [ ] **Step 3: Replace the cast with a normalizing accessor**

In `app/Models/Events.php`:

(a) Remove `'additional_data' => 'object',` from the `$casts` array. Leave the other casts.

(b) Add this accessor method to the class (place it near the other accessors, e.g. just after the `eventable()` relation or with the other `get*Attribute` methods). Add `use Illuminate\Database\Eloquent\Casts\Attribute;` to the imports if not already present:

```php
    /**
     * Normalize additional_data to an object on read.
     *
     * The column is JSON, but historically some rows were double-encoded
     * (a JSON string whose content is itself JSON). Decode until we have
     * an object so every reader (->public, ->times, ...) is safe regardless
     * of how the row was stored.
     */
    protected function additionalData(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null) {
                    return null;
                }

                $decoded = is_string($value) ? json_decode($value) : $value;

                // A double-encoded row decodes once to a JSON string; decode again.
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded);
                }

                return is_object($decoded) ? $decoded : null;
            },
        );
    }
```

Note on the raw `$value`: with the `object` cast removed, Eloquent passes the raw DB string to the accessor. `json_decode` of a normal row yields `stdClass`; of a double-encoded row yields a `string`, which the second `json_decode` resolves. A row that is a JSON array or scalar yields a non-object → returns `null` (additional_data is always expected to be an object map; null is the safe neutral value and existing readers already guard with `if ($this->additional_data && ...)`).

- [ ] **Step 4: Run the tests, confirm all pass**

Run: `docker-compose exec -T app php artisan test --filter=EventsAdditionalDataTest`
Expected: all 3 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Events.php tests/Unit/Models/EventsAdditionalDataTest.php
git commit -m "fix: normalize Events.additional_data to an object on read

Double-encoded JSON rows decoded to a string under the object cast,
crashing readers like additional_data->public. Replace the cast with
an accessor that decodes until it has an object.

Fixes TTS-BAND-11E"
```

---

## Task 3: Regression test — `ProcessEventCreated` survives a double-encoded row

**Files:**
- Test: `tests/Feature/ProcessEventCreatedTest.php` (create)

**Background:** Task 2 fixes the root cause (the accessor). This task adds a job-level regression test proving `ProcessEventCreated::handle()` no longer crashes on a double-encoded `additional_data` row — the exact production scenario of TTS-BAND-11E.

- [ ] **Step 1: Write the test**

Create `tests/Feature/ProcessEventCreatedTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\ProcessEventCreated;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessEventCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_does_not_crash_on_double_encoded_additional_data(): void
    {
        // A band with no calendars -> writeToGoogleCalendar returns false,
        // so the job exercises the additional_data->public branch without
        // needing real Google API calls.
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $booking->id,
        ]);

        // Double-encode additional_data, the exact corruption from TTS-BAND-11E.
        DB::table('events')->where('id', $event->id)
            ->update(['additional_data' => json_encode(json_encode(['public' => true]))]);

        // Must not throw "Attempt to read property public on string".
        (new ProcessEventCreated($event))->handle();

        // Job ran to completion; no Google event row created (no calendar).
        $this->assertDatabaseMissing('google_events', [
            'google_eventable_id'   => $event->id,
            'google_eventable_type' => Events::class,
        ]);
    }
}
```

- [ ] **Step 2: Run the test, confirm it passes**

Run: `docker-compose exec -T app php artisan test --filter=test_handle_does_not_crash_on_double_encoded_additional_data`
Expected: PASS (Task 2's accessor makes `additional_data->public` safe).

If it FAILS with "Attempt to read property public on string", Task 2's accessor is not covering the job's read path — investigate `ProcessEventCreated.php:48` and the accessor before proceeding.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/ProcessEventCreatedTest.php
git commit -m "test: ProcessEventCreated survives double-encoded additional_data"
```

---

## Task 4: Verification

- [ ] **Step 1: Run all four new/affected test files**

Run: `docker-compose exec -T app php artisan test --filter='ProcessBookingCreatedTest|EventsAdditionalDataTest|ProcessEventCreatedTest'`
Expected: all PASS.

- [ ] **Step 2: Run the broader calendar/event suite for regressions**

Run: `docker-compose exec -T app php artisan test --filter='Calendar|EventsToGoogle|BookingsToGoogle|EventObserver|BookingObserver'`
Expected: all PASS. The `additional_data` accessor change is the one with regression risk — if any existing test that reads `additional_data` fails, investigate before proceeding.

- [ ] **Step 3: Confirm clean state**

Run: `git status --short`
Expected: clean (only the unrelated untracked `.pki/` directory, if present).
