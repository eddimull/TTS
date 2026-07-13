# Mobile Song List Management + Songs↔Charts Relationship — Design

**Date:** 2026-07-13
**Status:** Approved
**Repos affected:** `TTS` (Laravel backend + web UI), `tts_bandmate` (Flutter mobile app)

## Problem

The band song list (repertoire) can only be created and edited on the web. The mobile
app consumes it read-only (global search, setlist editor picker) and has no song list
screen at all. The song list feeds the setlist builder today and the questionnaires
feature later, so mobile members need full management access.

Additionally, songs and charts (sheet music) are conceptually related but have no
relationship in the data model and appear as unrelated features to users. This design
unifies them in the UX and adds the backend relationship.

## Decisions (agreed with product owner)

1. **Full song list screen on mobile** — browse, search, add, edit; not just picker-level entry.
2. **Full field parity with web** — title, artist, key, genre, BPM (+ BPM lookup helper), notes, lead singer, transition song, rating, energy, active.
3. **Delete on mobile, owner-only** — matching web (`SongsController@destroy` requires `ownsBand()`).
4. **Proper `songs` token ability** — add `songs` to mobile token resources; do not keep piggybacking on `charts`.
5. **Navigation: segmented Library tab** — segments labeled **"Song list | Sheet music"**, plus a cross-link row in the hamburger Operations screen.
6. **Build the songs↔charts backend relationship now**, with linking UI on both mobile and web.
7. **Label charts as "Sheet Music" in all user-facing copy** (mobile and web). No backend renames: routes, tables, permission keys, API paths unchanged.
8. **Sub access to the song list is windowed** — a sub can read a band's songs only
   while scheduled on one of that band's events (accepted invitation or sub slot),
   including a 48-hour grace period after the event date. Applies to web and mobile
   (enforced in `User::canRead('songs')`). Subs never get write.

## 1. Data model (Laravel)

- New migration (generated via `php artisan make:migration`): add nullable
  `song_id` foreign key to `charts`, `constrained('songs')->nullOnDelete()`.
  Deleting a song unlinks its charts; it never deletes them.
- `Charts::song(): BelongsTo` and `Song::charts(): HasMany`.
- **Cardinality:** a song has many charts (arrangements); a chart belongs to at most
  one song. No pivot — a chart serving multiple songs is out of scope (migrate FK to
  pivot later if ever needed).
- **Tenancy guard:** every write that sets `song_id` on a chart validates
  `Song::where('id', $songId)->where('band_id', $chart->band_id)` (shared validation
  rule used by web and mobile).

## 2. Mobile API (Laravel, `/api/mobile`)

### Token abilities
- Add `songs` to `TokenService::RESOURCES` so tokens carry `read:songs` /
  `write:songs` derived from the web permission system (`user_permissions`).
- Re-gate existing `GET /api/mobile/bands/{band}/songs` from `mobile.band:read:charts`
  to `mobile.band:read:songs`.
- **Compatibility:** existing tokens lack the new ability; the app's 403 stale-token
  single-retry refresh re-mints abilities, and `band-member` role is seeded with
  `read:songs`, so existing installs recover transparently.

### Endpoints
| Method | URI | Gate | Notes |
|--------|-----|------|-------|
| GET | `/bands/{band}/songs` | `read:songs` | Expanded payload: all web fields + `lead_singer`, `transition_song`, linked charts (`id`, `title`). Default remains **active-only** (search + setlist picker unchanged); `?include_inactive=1` returns all for the management screen. |
| POST | `/bands/{band}/songs` | `write:songs` | Create; same rules as web. |
| PATCH | `/bands/{band}/songs/{song}` | `write:songs` | Update; song must belong to `{band}`. |
| DELETE | `/bands/{band}/songs/{song}` | `write:songs` + owner | Owner-only, matching web. |
| GET | `/songs/lookup` | authenticated | BPM lookup wrapping the existing `GetSongBpmService` (same as web `/songs/lookup`). |

- Mobile chart create/update (`storeChart` etc.) accept optional `song_id`
  (tenancy-validated). Chart payloads include the linked song (`id`, `title`);
  song payloads include linked charts.
- **Validation extracted to shared FormRequests** (e.g. `StoreSongRequest`,
  `UpdateSongRequest`) used by both the web `SongsController` and the mobile
  controller so rules cannot drift. Rules are the existing web rules verbatim
  (title required max:255; bpm 1–999; rating/energy 1–10; `lead_singer_id`
  exists:roster_members; `transition_song_id` exists:songs same band; etc.).
- Lead singer picker data comes from the existing mobile rosters API
  (`GET /api/mobile/bands/{band}/rosters` via `Api\Mobile\RostersController`,
  which exposes roster members). No new endpoint.

## 3. Flutter app (`tts_bandmate`)

### Navigation
- Library tab keeps its name/icon; a segmented control at the top switches
  **Song list | Sheet music**. The Sheet music segment is the current library screen
  unchanged below the segment; the Song list segment is the new feature.
- Hamburger Operations screen (`features/more/operations_screen.dart`) gains a
  `Song list` NavRow pushing the same songs screen (admin-task mental model).

### New feature `lib/features/songs/`
Modeled on `lib/features/library/` (repository / providers / screens / widgets / models):
- **Model:** one unified `Song` model (full field set + linked charts summary).
  Existing `SongResult` / `BandSong` / `BandSongSummary` are untouched;
  consolidation is a noted follow-up.
- **Repository:** Dio-based, constructor-injected, wrapped in a `Provider`.
- **Provider:** `AsyncNotifier` (like `LibraryNotifier`) with optimistic
  create/update/delete; watches `selectedBandProvider`.
- **Song list screen:** alphabetized, searchable, pull-to-refresh, active/inactive
  filter, add button. Add/edit affordances hidden without `write:songs`.
- **Song form screen** (create + edit via optional `existing`, per
  `booking_form_screen.dart` pattern): all fields; BPM field with lookup button;
  lead singer picker (roster); transition song picker (band songs); rating/energy
  steppers; active toggle. Full-screen Cupertino form per `create_chart_screen.dart`
  conventions (`_FormSection`, save-in-navbar, inline error banner).
- **Song detail screen:** all fields plus a **Sheet music section** listing linked
  charts (tap → existing chart detail screen).
- **Delete:** owner-only, confirmation dialog, long-press pattern matching library.

### Sheet music (chart) screens
- Chart create/edit form gains an optional **"Linked song" picker** (band songs).
- Chart detail shows the linked song.
- All user-facing "Chart(s)" copy in the app becomes "Sheet music".

## 4. Web UI (Laravel/Inertia)

- **Chart side (primary linking point):** `Charts/Edit.vue` + create flow get an
  optional "Linked song" select (band's songs); `ChartsController@store/update`
  accept `song_id` via the shared validation. `Charts/Show.vue` displays the
  linked song.
- **Song side:** `Songs/Index.vue` rows/edit dialog show linked charts as chips
  linking to the chart Show page; `SongsController@index` eager-loads
  `charts:id,title,song_id` (no N+1).
- **Labeling:** visible nav label and page headings for charts change to
  "Sheet Music". Historical strings (emails, PDFs) are not swept.

## 5. Error handling

- Flutter: `ErrorView` / `ErrorView.friendlyMessage` for load failures, inline
  dismissible error banner in forms, `EmptyStateView` for empty list, existing
  401/403 interceptor behavior for auth/permission errors.
- Laravel: standard 403 from ability middleware / owner checks; 422 validation
  errors; cross-band writes rejected by validation (404/422, matching existing
  `EnsureUserInBand` behavior).

## 6. Testing (TDD throughout)

**Laravel (feature tests, `tests/Feature/Api/Mobile/` + `tests/Feature/`):**
- Mobile songs CRUD: happy paths; missing ability → 403; cross-band song/band
  mismatch rejected; owner-only delete (member with write → 403); inactive
  filtering with/without `include_inactive`; expanded payload shape.
- Chart↔song linking: link on create/update; cross-band `song_id` rejected;
  song delete nulls `charts.song_id`.
- BPM lookup endpoint (service faked).
- **Backfill web `SongsController` tests** (currently none) since its validation
  moves to FormRequests; plus `ChartsController` link coverage.
- Test method names use the `test_` prefix (project convention).

**Flutter (`test/features/songs/**`, mirroring `test/features/library/**`):**
- Repository tests (request shape, parsing), provider tests (optimistic updates,
  error states) with `ProviderContainer` + fakes, screen/widget tests for list,
  form, detail, and the segmented Library tab.

## Out of scope / follow-ups

- Questionnaires feature (future consumer of the song list).
- Web UI beyond linking (song page redesign, chart-side bulk linking).
- Consolidating the app's three legacy song models onto the unified `Song` model.
- Sweeping historical "chart" strings in emails/PDFs.
- Many-to-many song↔chart (medleys).
