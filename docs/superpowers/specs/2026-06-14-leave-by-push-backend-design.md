# Leave-By Push Notification Backend — Design

**Date:** 2026-06-14
**Status:** Approved for planning
**Repo:** TTS (Laravel 12 backend). PRs target **staging**. All artisan/composer/phpunit run via `docker compose exec app …`.
**Companion:** the Flutter mobile app's leave-by notifications (Phase 1 push plumbing + Phase 2 location enrichment) is already built and merged. This backend is the piece that actually *sends* the pushes the app is built to receive.

## Summary

Build the backend service that registers mobile device tokens, then on a schedule selects the events each band member is rostered for today and sends two data-only push notifications per event (an 8-hours-before "you have a gig today" reminder and a departure trigger that wakes the device to compute live travel time). FCM delivers to both Android and iOS. The backend owns delivery and timing; the device owns location enrichment.

## Goals

- Implement the two device endpoints the mobile app already calls: `POST /api/mobile/devices`, `DELETE /api/mobile/devices/{token}`.
- Send data-only FCM messages matching the app's Phase 1 payload contract (flat camelCase, no `notification` block).
- Fire two notifications per event per day, at per-event times computed in the **venue's** timezone.
- Be idempotent (never double-send) and self-recovering after a missed tick.
- Notify only active roster members who have a registered device.

## Non-Goals

- Server-side travel-time / location (the device computes that — Phase 2, already built).
- Subs/dep musicians (roster members only for v1; subs are a possible fast-follow).
- A general notification-preferences UI (out of scope for v1).
- Per-band timezone *configuration* UI (timezone is derived from the venue address, not entered).

## Architecture

A Laravel 12 subsystem with four cooperating parts:

1. **Device registration** — `device_tokens` table + `DeviceTokenController` exposing `POST /api/mobile/devices` (upsert token+platform for the authed Sanctum user) and `DELETE /api/mobile/devices/{token}`.

2. **FCM sending** — `kreait/laravel-firebase` configured with a backend Firebase service account. A thin `FcmSender` sends **data-only** messages. FCM proxies to APNs for iOS using the APNs key already uploaded in the mobile Phase 1 setup — one code path for both platforms. Invalid/unregistered tokens are pruned from `device_tokens`; transient errors log and let the queue retry.

3. **Scheduling tick** — a console command `notifications:tick` registered in the scheduler every **5 minutes** (mirroring the existing `horizon:snapshot` cadence). Each tick finds today's rostered events, computes each event's two send-times in the venue timezone, and sends any whose time falls in the current window and isn't already logged.

4. **Payload building** — reuses the existing mobile `EventsController` timeline mapping (extracted to a shared `EventTimelineMapper`) so the push's `firstItemTitle`/`firstItemTime`/`showTime` match exactly what the app's detail screen shows.

**Recipients:** users in `event_members` (attendance_status not in `absent`/`excused`, not soft-deleted) who have at least one row in `device_tokens`.

**Boundary:** backend owns delivery + timing (time-based text); the device owns location enrichment (upgrades/suppresses locally).

## Data Model

### New table: `device_tokens`
- `id`, `user_id` (FK → users, indexed), `token` (string, unique), `platform` (enum `ios`/`android`), `timestamps`.
- One row per device; upserted by `token`. Deleted when FCM reports the token dead.

### New table: `push_notification_log` (idempotency ledger)
- `id`, `event_id` (indexed), `user_id`, `type` (`event_reminder_8h` | `event_departure`), `sent_at`, `timestamps`.
- **Unique index on `(event_id, user_id, type)`.** A row's existence means "already sent." The hard cap of 2 notifications/event/user/day falls out of this. Per-user (not per-device) so a user with two devices isn't re-notified.

### New column: `events.venue_timezone` (nullable string)
- Caches the resolved IANA timezone for the event's venue, populated lazily on first send-time computation.

### New models
- `DeviceToken` (`belongsTo User`), `PushNotificationLog` (`belongsTo User`, `belongsTo Events`).

## Components

New classes in `app/Services/`:

- **`EventTimelineMapper`** — extracted from the inline logic in `app/Http/Controllers/Api/Mobile/EventsController.php` so the API and the push share one definition of "timeline → first item / show time." Targeted refactor: pull the mapping out of the controller, have the controller call it, and reuse it here. `firstItem(event)` returns the timeline entry with the earliest parseable time; `showTime(event)` returns `event.start_time`.

- **`VenueTimezoneResolver`** — `forEvent(event): string` (IANA zone). If `event.venue_timezone` is set, return it. Else geocode `event.venue_address` (reuse the app's existing Google key) → lat/lng → Google Time Zone API → IANA zone; cache it on `events.venue_timezone`. On any failure, return `config('app.timezone')` **without** caching (so it retries later).

- **`LeaveByPushService`** — orchestrator. `run(Carbon $now)`: select today's rostered events; per event compute send-times via `VenueTimezoneResolver` + `EventTimelineMapper`; check `push_notification_log`; dispatch sends to recipients.

- **`FcmSender`** — wraps `kreait` to send one data-only message to one token; returns `delivered` / `prune` (dead token) / `transient` (retryable).

New controller: **`DeviceTokenController`** (`store`, `destroy`).
New console command: **`notifications:tick`** → `LeaveByPushService::run(now())`.

## Data Flow

The tick (every 5 minutes):

```
notifications:tick → LeaveByPushService.run(now = Carbon::now())
  1. Select today's events (across all bands) that have rostered members.
     "Today" evaluated with a margin (now ± a few hours) so venue-tz events near
     midnight aren't missed.
  2. For each event (wrapped in try/catch — one bad event never aborts the run):
     a. tz    = VenueTimezoneResolver.forEvent(event)        // cached on event
     b. first = EventTimelineMapper.firstItem(event)         // earliest timeline time
        show  = event.start_time
     c. In tz:
          firstItemDateTime = event.date + first.time (or start_time if no timeline)
          send8h        = firstItemDateTime - 8h
          sendDeparture = firstItemDateTime - DEPARTURE_LEAD_MINUTES (default 90)
     d. For each send-type whose window contains now
        (send-time ≤ now < send-time + GRACE_WINDOW, grace ≈ 30min > tick width):
          recipients = rostered EventMembers (not absent/excused) with a device,
                       excluding (event,user,type) already in push_notification_log
          for each recipient:
            build data-only payload → dispatch queued send job → FcmSender per token
            on success: insert push_notification_log (event_id, user_id, type)
            on dead token: delete device_tokens row
```

### Send-time definitions
- **`event_reminder_8h`**: 8 hours before the first timeline item (or `start_time` if no timeline). The safety-net "you have a gig today."
- **`event_departure`**: `DEPARTURE_LEAD_MINUTES` (default **90**) before the first item. Fires early enough that the device's computed "leave in 15 min" almost always still lies in the future. Tunable constant.

### Data-only payload (matches the mobile Phase 1 contract exactly — flat camelCase, no `notification` block)
```
data: {
  type: "event_reminder_8h" | "event_departure",
  eventKey: <event.key>,
  title: <event.title>,
  venueAddress: <event.venue_address>,
  firstItemTitle: <first.title>,
  firstItemTime: <ISO8601 in venue tz>,
  showTime: <event.start_time as ISO8601 in venue tz>
}
```
Optional fields (`venueAddress`, `firstItemTitle`, `firstItemTime`, `showTime`) are omitted when absent. `type`, `eventKey`, `title` always present.

### Idempotency & catch-up
- The `(event_id, user_id, type)` unique index makes a duplicate send a no-op insert (caught and ignored).
- The "due" check uses `send-time ≤ now < send-time + GRACE_WINDOW` with a grace window (~30 min) wider than the 5-min tick, so a single missed tick (deploy/downtime) still fires (slightly late) rather than being skipped.

## Error Handling

- **Geocode / Time Zone API failure** → `VenueTimezoneResolver` falls back to `config('app.timezone')`, logs a warning, does **not** cache the fallback (retries next time).
- **FCM per-token result:** `unregistered`/`invalid-argument` → delete that `device_tokens` row. Transient (5xx/timeout) → log; queue retry handles it. Per-token try/catch so one bad token never blocks others.
- **No devices / no timeline / no venue:** skip that send-type silently.
- **The tick** wraps each event in try/catch (log + continue) so one bad event can't abort the run.
- **Queue:** sends dispatched as queued jobs (Horizon) so a slow FCM call doesn't stall the tick. `onOneServer()` on the schedule entry prevents double-runs across servers.

## Testing (PHPUnit + factories, `tests/Feature` + `tests/Unit`)

- **Device endpoints (Feature):** register upserts by token; register requires `auth:sanctum`; delete removes only the caller's token.
- **`EventTimelineMapper` (Unit):** first-item-is-earliest, show-is-`start_time`, empty/garbage timeline. Also assert the refactor leaves the existing `EventsController` output unchanged.
- **`VenueTimezoneResolver` (Unit):** Google client faked (no live API) — returns zone, caches on event, falls back to app tz on failure (and does not cache the fallback).
- **`LeaveByPushService` (Feature):** `FcmSender` faked, time frozen with `Carbon::setTestNow` (pinned — no live `now()` in assertions): due-window selection, idempotency (second run sends nothing), recipient filtering (absent/excused excluded, no-device excluded), dead-token pruning, per-event isolation on error, the 8h vs departure timing.
- **Payload shape (Feature/Unit):** assert the exact data-only camelCase keys the app's `PushPayload.fromData` expects — this is the cross-repo contract.

All commands run via `docker compose exec app …`.

## Prerequisites (manual, external)

- **Firebase service account JSON** for the backend (Firebase console → Project Settings → Service accounts → Generate new private key). Store as a secret; reference from `kreait` config. This is the same Firebase project the mobile app's Phase 1 used.
- **Enable the Google Time Zone API** on the existing Google key used for geocoding.
- Confirm the queue worker / Horizon is running in the target environment so dispatched send jobs are processed.

## Open Items / Tuning (implementation-time)

- `DEPARTURE_LEAD_MINUTES` (default 90) and `GRACE_WINDOW` (~30 min) — finalize during implementation.
- "Today" selection margin for venue-tz edge cases near midnight.
- Whether to batch FCM sends (kreait supports multicast) vs. one job per recipient — start simple (per recipient), optimize if volume warrants.
