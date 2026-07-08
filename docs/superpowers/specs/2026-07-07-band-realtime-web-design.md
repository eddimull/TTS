# Band realtime on web (Vue/Inertia consumer) — design

**Date:** 2026-07-07
**Repo:** TTS (branch `feat/band-realtime-web`, stacked on `feat/band-realtime-broadcasts`)
**Status:** Approved
**Companion:** mobile consumer spec lives in tts_bandmate
`docs/superpowers/specs/2026-07-06-band-realtime-invalidation-design.md`

## Problem

The backend now broadcasts thin `BandDataChanged` signals
(`.band.data-changed` on `private-band.{bandId}`, payload
`{model, id, action[, parent]}`) and mobile consumes them. Web pages still go
stale until a full page load. Web should consume the same signals: silent
partial-reloads of affected Inertia props, plus a notifications bell that
updates live and visibly.

## Decisions made

- **Scope:** every page whose Inertia props are fed by the five broadcasting
  models (`bookings`, `events`, `rehearsal`, `roster`, `event_member`).
  Pages backed by non-broadcasting models (songs, media, roles, contacts)
  are out until those models adopt the trait.
- **Refresh UX:** silent auto-refresh via `router.reload({ only: [...] })`
  (the app's existing idiom), debounced. Exception: the TTSNotifications
  bell updates live AND visibly (badge/dropdown refresh + toast on new).
- **Architecture:** per-page opt-in composable + one always-on layout
  listener for the bell (approach A). No global convention-based reloads.

## Components

### 1. Shared band context (backend, small)

`HandleInertiaRequests::share` adds `auth.user.band_ids`: deduped ids of the
user's owned + member + sub bands (same audience the `band.{bandId}`
channel authorizes). Absent when unauthenticated.

### 2. `useBandRealtime` composable — the one new moving part

`resources/js/composables/useBandRealtime.js`:

```js
useBandRealtime(bandIdOrIds, reloadMap, options = {})
// reloadMap: { bookings: ['bookings'] }                        — model → props
//        or  { bookings: { props: ['booking'], when: p => p.id === X } }
// options: { onSignal(payload) }  — non-reload consumers (the bell)
```

- Subscribes `window.Echo.private('band.' + id)` per id,
  `.listen('.band.data-changed', ...)` — **leading dot** (`broadcastAs`
  convention, same as `.SetlistSessionStarted`).
- Debounce ~300 ms: coalesce arriving signals, then ONE
  `router.reload({ only: [union of mapped props] })`.
- `when` predicates gate detail pages (only my booking id).
- **Module-level channel refcounting:** Inertia page transitions can have
  the outgoing page `leave()` a channel the incoming page just subscribed;
  refcount and only `Echo.leave()` at zero (web twin of the mobile
  generation-guard fix).
- `.subscribed()` no-op; `.error()` → `console.warn` only (no toast spam
  for a background feature).
- Lifecycle: `onMounted`/`onUnmounted` (usable from `<script setup>`; the
  Options-API layout calls it from a `setup()` block).

### 3. Live bell (Authenticated.vue)

Layout subscribes to all `auth.user.band_ids` via the composable with an
`onSignal` handler (no reloadMap): debounced refetch of the shared `auth`
prop (`router.reload({ only: ['auth'] })`), which feeds the existing Vuex
notification store → badge + dropdown update in realtime. When the unread
count increases, surface the newest notification through the existing
toast mechanism. Backend jobs (`ProcessBookingUpdated` / `ProcessEventUpdated`
/ invitation services) already create the TTSNotifications; this makes
them appear without a page load.

### 4. Page opt-ins (the sweep)

One `useBandRealtime(...)` line per page. Initial mapping (plan-time
controller sweep finalizes the list — any page whose `Inertia::render`
props come from the five models):

| Page | band id source | models → props |
|---|---|---|
| Dashboard | `auth.user.band_ids` (all) | events, event_member, roster → `events` |
| Bookings/Index | `auth.user.band_ids` (multi-band page) | bookings → `bookings` |
| Bookings/Show + sub-pages (Payout, Contract, Contacts, Media) | `booking.band_id` | bookings (when id matches) → `booking`, `recentActivities` (per page's props) |
| Events/Index | page band or `auth.user.band_ids` | events, event_member, roster → `events` |
| Band rosters management | page band | roster → `rosters` |
| Rehearsals pages (if Inertia-rendered) | page band | rehearsal → their props |

### 5. Noise & self-echo

Own mutations already reload after POST; the self-signal coalesces into
the debounce — at worst one redundant partial reload. No `toOthers()`
(deferred, same as mobile). Timestamp-touch signals are absorbed by the
debounce.

## Error handling

- Echo/channel failure → silent (console.warn); pages behave exactly as
  today (stale until navigation).
- Unauthorized channel (sub without read) → Echo `.error()`, same silence.
- Reload requests failing → Inertia's normal error handling; no retry loop.

## Testing

First Echo tests in the web suite (vitest + jsdom):
- `window.Echo` mock in `resources/js/tests/mocks/` (channel registry,
  fire helper).
- Composable: refcounting across mount/unmount overlap, debounce
  coalescing, model→prop union, `when` filter, leading-dot event name,
  `onSignal` path (mock `router.reload`).
- Bell handler: signal → auth-prop reload call; count-increase → toast.
- CI note: assert on rendered text, never the `<!--v-if-->` marker.

## Delivery

Branch `feat/band-realtime-web` (this branch) stacked on
`feat/band-realtime-broadcasts`; PR targets `staging` after TTS #516
merges. Backend surface in this PR: only the `HandleInertiaRequests`
shared-prop addition (+ its test).

## Out of scope

- Broadcasting for songs/media/roles/contacts models (one trait line each,
  later).
- `toOthers()` sender suppression (tracked with mobile's deferral).
- Presence/typing indicators, per-ability channels.
- The web planner-chat consumer (none exists today; unrelated).
