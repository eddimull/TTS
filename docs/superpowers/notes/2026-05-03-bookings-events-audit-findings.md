# Bookings/Events Pre-Migration Audit — Findings

**Date:** 2026-05-03
**Spec:** `tts_bandmate` repo, `docs/superpowers/specs/2026-05-03-bookings-events-relationship-design.md` (commit `4f555f1`)
**Plan:** `tts_bandmate` repo, `docs/superpowers/plans/2026-05-03-bookings-events-chunk-0-data-audit.md`
**Audit branch:** `chore/bookings-events-audit` in this repo

## Summary

The audit ran against a recent production snapshot (606 bookings + 2 to total 608) loaded into a local `tts_audit` database. The result clears Chunk 1 to proceed, with one addition to the migration logic.

```
Total bookings: 608
  OK: 462
  NO_EVENTS: 146
  DATE_MISMATCH: 0
  VENUE_NAME_MISMATCH: 0
  VENUE_ADDRESS_MISMATCH: 0
MULTI_EVENT: 2 (informational; not flagged)
FLAGGED: 146
```

## Verdict: Green (with one migration addition)

Chunk 1 may proceed. The migration must be extended to **backfill an event for every NO_EVENTS booking** before it copies booking-level `start_time`/`end_time`/`venue_name`/`venue_address` onto each booking's primary event. After backfill, the spec's invariant ("every booking has ≥1 event") holds for all 608 rows and the existing copy logic works uniformly.

No date or venue mismatches were found, so no per-booking manual reconciliation is needed. No spec change is required.

## Findings by category

### 462 OK bookings (76%)

The chronologically-first event under each booking has a `date` matching the booking's `date` and either matching or empty venue fields. Chunk 1's copy logic works as-is for these.

### 146 NO_EVENTS bookings (24%)

These are bookings with zero rows in `events` where `eventable_type = 'App\Models\Bookings'`. Investigated further with three follow-up queries:

**Status breakdown:**
- 90 confirmed
- 41 pending
- 9 cancelled
- 6 draft

**Age breakdown by booking date year:**
- 2026: 2
- 2025: 7
- 2024: 29
- 2023: 44
- 2022: 52
- 2021: 12

**Financial activity:**
- 177 payment records attached to these 146 bookings (so ~30 bookings have multiple payments).
- 189 contract records attached (similarly).

**Interpretation:** these are real, processed bookings — many with money and contracts attached — that simply pre-date whatever code change introduced auto-event creation. The age distribution (overwhelmingly 2021–2024, with only 9 from 2025+) supports this. There is no data corruption to fix; this is historical schema evolution showing through.

**Decision:** backfill an event for each in Chunk 1's migration, applied uniformly across all status values (including cancelled and draft). The status field already conveys "this didn't happen" or "this was abandoned"; treating those bookings as event-less in the new model would complicate the migration without value.

The backfilled event for each NO_EVENTS booking gets:
- `eventable_type = 'App\Models\Bookings'`, `eventable_id = booking.id`
- `date = booking.date`
- `title = booking.name`
- `event_type_id = booking.event_type_id`
- After the column-add step, `start_time`, `end_time`, `venue_name`, `venue_address` are then copied from the booking row by the same general copy step that handles primary events of OK bookings.

### 2 MULTI_EVENT bookings (informational)

Spot-checked manually:

- **Booking 524 — "Blue Martini Labor Day Bonanza"** — confirmed, 3 events on 2025-08-29 / 08-30 / 08-31. Booking-level `date = 2025-08-29` matches the chronologically-first event. Booking's `start_time = 20:00`, `end_time = 00:00`, venue = "Blue Martini Lounge". Looks like a 3-night residency. The first event is the correct copy target.
- **Booking 526 — "Blue Martini Santagasm"** — confirmed, 2 events on 2025-12-26 / 12-27. Booking-level `date = 2025-12-26` matches the first event. Same venue. 2-night stand. First event is correct.

Both are exactly the use case the redesign is meant to support — multi-night engagements that today shoehorn one set of times/venue onto the booking row, with extra event rows added manually as a workaround. After the redesign these become first-class multi-event bookings.

No special handling required for these; the standard copy logic handles them correctly.

## Implications for Chunk 1's plan

The Chunk 1 spec section in the design doc says:

> Migration: add new event columns; for each booking, copy `start_time`/`end_time`/`venue_name`/`venue_address` from the booking row onto the **primary event** (the chronologically-first event by `(date ASC, id ASC)`; for the common case of a single auto-event, this is the only event). Bookings flagged by the Chunk 0 audit get manual handling. Drop booking columns after copy is verified.

Update for Chunk 1's plan:

1. **Before the column-copy step**, insert one event for each booking with zero events, populated from the booking-level `name`, `date`, `event_type_id`. This step runs as part of the same migration.
2. After backfill, every booking has ≥1 event and the existing column-copy logic applies uniformly.
3. No "manual handling" branch is needed — there are no flagged rows that require per-booking decisions.

## Re-runnability

The audit command can be re-run any time post-Chunk-1. After Chunk 1, `events.venue_name` / `events.venue_address` will be populated, and any divergence between a booking and its primary event's venue will surface as a real mismatch (not a false positive from missing columns). This makes the command useful as a sanity check during and after the migration.

## Artifacts

- Audit summary: stdout output above.
- Per-booking CSV: `/tmp/audit-report.csv` (462 OK rows, 146 NO_EVENTS rows; not committed — local-only artifact).
- Audit command source: `app/Console/Commands/AuditBookingEventDataCoverage.php` (commit `c1bf1fe4`).
