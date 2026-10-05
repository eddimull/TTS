# Mobile Notifications (web bell → app) — Design

**Date:** 2026-10-05
**Status:** Approved (pending implementation)
**Repos:** TTS (Laravel backend, first) and tts_bandmate (Flutter, second). Both plans reference this spec.

## Goal

Bring the web "bell" — Laravel database notifications — to the mobile app: an in-app feed with a badge, deep links that land on the right screen, live freshness, and push notifications for every notification type that today reaches only the web bell and email.

## Decisions log

- **Push parity: push them all.** Every database notification a `User` receives also pushes to their devices (text + deep link). Types that already have their own push job (chat, rehearsal cancel/restore, questionnaire submitted) are not doubled.
- **Mobile entry: bell in the Dashboard nav bar** (between `+` and the avatar), badge = unseen count, opens `/notifications`.
- **Freshness: realtime signal + resume refresh** — a thin `user.data-changed` broadcast with model `notification` on the user's private channel, plus refetch on app resume.
- **Deep links are resolved server-side** (`kind` + mobile `deeplink` in the API), not by a Dart mapper and not by rewriting stored payloads.
- **Rollout:** backend first, with the new push listener behind a config flag that stays off until the mobile release is live in the stores.

## Current state (verified 2026-10-05)

- Table `notifications` (Laravel default + custom `seen_at`); model `App\Models\Bandnotification extends DatabaseNotification` adds `markAsSeen()`. `User::notifications()` is overridden with `limit(50)` newest-first.
- Web routes (`routes/notifications.php`, session auth): list (50 rows), mark read, read-all, "seen" (which iterates *unread* rows — a bug). Web bell badge counts `seen_at === null`; row highlight is `read_at === null`; href is `route(data.route, data.routeParams)` via Ziggy.
- 16 `App\Notifications\*` classes. Database-channel ones: `TTSNotification` (9 call sites), `CommentPosted`, `DirectMessageReceived`, `RehearsalCancelled`, `BandPaymentReceived`, `QuestionnaireSubmitted`, `QuestionnaireSent` (contacts only), `MediaUploadedNotification` (contacts only), `EventAdded`/`EventUpdated` (dead). Payload `routeParams` appears as assoc array, bare scalar, `null`, or `''`.
- Data defects: `ProcessEventUpdated` writes `route => 'Event Details'` (no such route — the web bell link throws); `BandsController@uploadLogo` writes `route => 'bands'` while `url` says `/bands/{id}/edit`; `RehearsalCancelled` has no `route`, only `link` + `rehearsal_id`.
- Push today: `SendUserPush::dispatch($userId, $data, $dedupeKey, $alert, $androidTag)` → `FcmSender`. Only chat, rehearsal cancel/restore/sub-added/removed, questionnaire submitted and leave-by push. All `TTSNotification` and `BandPaymentReceived` rows are bell + email only.
- Mobile: no notifications screen or endpoint. `lib/features/notifications/` holds push plumbing only: `push_payload.dart` (`PushType` enum, `PushPayload.fromData`), `push_route.dart` (`routeForPushData`: `chat_message`, `questionnaire_submitted`, `rehearsal_*`), `PushService`, `DeviceRepository` (`/api/mobile/devices`). Realtime: `userRealtimeProvider` listens on `private-App.Models.User.{id}` for `user.data-changed` (model `message` today). Badge pattern: `AppScaffold._tabIcon` + `chatUnreadTotalProvider`. Dashboard nav bar trailing: `+` button, avatar → `/account`.
- Mobile routes: `/events/:key`, `/bookings/:bandId/:id`, `/rehearsals/:id`, `/conversations/:id`, `/questionnaires/:id/instances/:instanceId`, `/band-settings` (owner-only), `/dashboard`.

## Backend (TTS)

### 1. `App\Services\Notifications\NotificationPresenter`

`present(Bandnotification $n): array` →
```
{ id, kind, text, deeplink, web_url, read_at, seen_at, created_at }
```
- `kind` ∈ `booking | event | rehearsal | conversation | band | questionnaire | dashboard`.
- `deeplink` is a mobile route: `/bookings/{band}/{booking}`, `/events/{key}`, `/rehearsals/{id}`, `/conversations/{id}`, `/questionnaires/{id}/instances/{instanceId}`, `/band-settings`, `/dashboard`.
- Resolution order per row: explicit ids in `data` (`conversation_id`, `rehearsal_id`, `instance_id`+`questionnaire_id`) → `data.route` + `routeParams` (assoc / scalar / null / '' all accepted) → `data.url`/`link` path patterns (`/events/{key}`, `/bands/{band}/booking/{booking}`, `/bands/{id}/edit`) → fallback `dashboard`.
- Special cases: `Event Details` + `routeParams.event` → look up the event's `key` → `/events/{key}`; `events.advance` → `/events/{key}` (no mobile advance sheet); `bands`/`bands.edit` → `/band-settings`; a missing or deleted target → `dashboard`.
- `text` = `data.text` (fallback `data.message`, else a per-kind default like "New notification").
- `web_url` = the corrected web path for the same target (so the web bell can later use it instead of Ziggy). Switching the web bell is a follow-up.
- `kind: band` deeplinks go to `/band-settings`, which is owner-only on mobile; the API resolves `/dashboard` for non-owners of that band (presenter takes the viewer).

### 2. Mobile API (`routes/api.php`, `auth:sanctum` group, `Api\Mobile\NotificationsController`)

| Verb | URI | Name | Returns |
|---|---|---|---|
| GET | `/api/mobile/notifications?cursor=&limit=30` | `mobile.notifications.index` | `{ notifications: [presented…], next_cursor, unseen_count }` — newest first, cursor = `created_at|id` of the last row; queries `Bandnotification` directly (not the limited relation) |
| GET | `/api/mobile/notifications/unseen-count` | `mobile.notifications.unseen` | `{ count }` |
| POST | `/api/mobile/notifications/{notification}/read` | `mobile.notifications.read` | 204; 404 unless the row belongs to the user |
| POST | `/api/mobile/notifications/read-all` | `mobile.notifications.read-all` | 204 |
| POST | `/api/mobile/notifications/seen` | `mobile.notifications.seen` | 204 — marks every row with `seen_at IS NULL` (correct semantics; the web `/seentIt` bug is not replicated) |

No new token ability (notifications are personal, band-agnostic; the base `mobile` ability suffices).

### 3. Realtime

`App\Events\NotificationChanged` (`ShouldBroadcastNow`, channel `private-App.Models.User.{id}`, `broadcastAs 'user.data-changed'`, payload `{ model: 'notification', id, action: created|read|seen }`), fired by: a `NotificationSent` listener (database channel, `User` notifiable) for `created`; the read/read-all/seen endpoints and the existing web routes for `read`/`seen`. `toOthers()` is not used (the acting device should also refresh).

### 4. Push parity — `App\Listeners\PushDatabaseNotification` + `App\Jobs\SendNotificationPush`

- Listener on `Illuminate\Notifications\Events\NotificationSent`: only when `channel === 'database'`, notifiable is a `User`, `config('push.notifications_feed')` is true, and the notification class is not in the "has its own push" allowlist (`CommentPosted`, `DirectMessageReceived`, `RehearsalCancelled`, `QuestionnaireSubmitted`). Dispatches `SendNotificationPush($notificationId)`.
- Job: loads the `Bandnotification`, runs the presenter (for the recipient), then `SendUserPush::dispatch($userId, ['type' => 'notification', 'notificationId' => (string) id, 'kind', 'title' => band or app name, 'body' => text, 'deeplink'], 'notification:' . id, alert: true)`.
- Config flag `PUSH_NOTIFICATIONS_FEED` (default **false**) in `config/push.php` (new file) — flipped on after the mobile release ships.

### 5. Payload corrections at write time

`ProcessEventUpdated` → `route => 'events.show'`, `routeParams => ['key' => $event->key]`, `url => /events/{key}`. `BandsController@uploadLogo` → `route => 'bands.edit'`, `routeParams => $band->id`. (The presenter still handles old rows.)

## Mobile (tts_bandmate)

### 6. Data + providers (`lib/features/notifications/`)

- `data/models/notification_item.dart`: `NotificationItem { int id; String kind; String text; String deeplink; DateTime? readAt; DateTime? seenAt; DateTime createdAt }` with `fromJson` (null-safe like the other models).
- `data/notification_repository.dart`: `list({cursor, limit})` → `NotificationPage { items, nextCursor, unseenCount }`; `markRead(id)`, `markAllRead()`, `markSeen()`, `unseenCount()`. Endpoints added to `api_endpoints.dart`.
- `providers/notification_feed_provider.dart`: `AsyncNotifier` with `loadMore()`, `refresh()`, `markRead(id)` (optimistic), `markAllRead()`; `unseenNotificationsCountProvider` (`FutureProvider<int>`, SWR-cached like the other counts).
- Realtime: `userRealtimeProvider` gains `model == 'notification'` → invalidate feed + count. Resume: the existing `AppLifecycleListener` pattern refreshes both.

### 7. UI

- **Dashboard nav bar bell**: `CupertinoButton` with `CupertinoIcons.bell` (+ `bell_fill` when unseen > 0) and the red count pill (same widget as the Messages tab badge, factored into a shared `UnreadBadge`), `Semantics(label: 'Notifications, N unseen')`, pushes `/notifications`.
- **`NotificationsScreen`** (`/notifications`, pushed, not a shell route; added to restorable prefixes): `CupertinoPageScaffold` + sliver nav bar ("Notifications", trailing "Mark all read"); list grouped by day (Today / Yesterday / date headers, reusing the chat date helpers); row = kind icon (briefcase, calendar, music note, chat bubble, group, doc, home), text (2 lines max), relative time, blue dot while unread. On first frame: `markSeen()`; the badge clears. Tap: optimistic `markRead(id)` then `context.go(item.deeplink)`. Infinite scroll on the cursor; pull-to-refresh; empty state "You're all caught up"; error state with retry. Dark mode via `context.secondaryText` conventions.
- **Push**: `push_payload.dart` adds `PushType.notification` (`type: 'notification'`, fields `notificationId`, `deeplink`, `kind`); `push_route.dart` returns `data['deeplink']` for it; `buildBackgroundNotification` renders it on the band-updates channel; foreground renders locally like chat (no suppression rules).
- **Deep-link safety**: `context.go` only for routes that need no `state.extra` (all of the resolver's outputs qualify).

## Error handling

- API failures: feed shows the error state with retry; badge keeps its last value.
- `markSeen`/`markRead` failures are best-effort (optimistic UI stays; next refresh reconciles).
- A deeplink to something the user can no longer access lands on that screen's own 403/404 handling.
- Unknown `kind` on mobile → generic icon; unknown `deeplink` → `/dashboard`.

## Testing

**Laravel** (`tests/Feature/Api/Mobile/Notifications/`): presenter table test across every class/payload shape listed above (incl. the three defects and the non-owner band case); index pagination past 50 rows + `unseen_count`; read/read-all/seen semantics (seen marks only unseen); 404 cross-user; `NotificationChanged` broadcast on created/read/seen; push listener: dispatches for `TTSNotification`/`BandPaymentReceived` with `type: notification` + deeplink, skips allowlisted classes, respects the config flag, dedupes by id; existing `ChatPushTest`, `RehearsalCancelledNotificationTest`, questionnaire tests unchanged.

**Flutter** (`test/features/notifications/`): model parsing; repository with fake Dio; feed provider pagination/optimistic read/realtime invalidation/resume; `routeForPushData` for `notification`; `PushPayload` parsing; widget tests for `NotificationsScreen` (rows, unread dot, mark-all, empty/error) and the Dashboard bell badge at `Size(320, 568)`.

**On-device** (run-on-device skill, SM-G998U, local backend): change a booking status on the web → badge appears on the phone; open the feed → badge clears, row shows; tap → booking detail; flip `PUSH_NOTIFICATIONS_FEED` locally → the next status change arrives as a push and taps through; web bell unaffected.

## Rollout

1. TTS PR (backend, flag off) → staging → prod.
2. tts_bandmate PR with version bump → TestFlight/Play rollout.
3. Flip `PUSH_NOTIFICATIONS_FEED=true` in prod once the store build is live.

## Out of scope

Switching the web bell to `web_url` (follow-up); notification preferences/muting; pushes for contact-facing notifications (`QuestionnaireSent`, `MediaUploadedNotification` go to `Contacts`, not users); email changes.
