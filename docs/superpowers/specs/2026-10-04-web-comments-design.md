# Web Comments (chat parity, slice 1) — Design

**Date:** 2026-10-04
**Status:** Approved (pending implementation)
**Repo:** TTS (Laravel + Inertia/Vue 3). No mobile change.
**Parent spec:** `tts_bandmate/docs/superpowers/specs/2026-07-12-comments-chat-design.md` (data model, policy, wire contract, realtime rails). This document only covers the web phase that spec deferred.

## Goal

Bring the mobile comments experience to the web app: a full-parity comment thread on event, rehearsal, and booking detail pages, unread-comment indicators on the dashboard, bell notifications for new comments, and `moderate:chat` grantable from the web permissions page.

This is slice 1 of four. Later slices (separate specs): **Messages** (conversation list, DMs, band channel, nav entry), then anything left over. The thread component built here is reused by Messages unchanged.

## Decisions log

- Order: access layer + comments first; Messages second.
- Comments UI: a **slide-over drawer** opened from a header button on all three detail pages (not inline sections, not a booking tab).
- Thread scope: **full parity now** — text, images, edit, delete/moderate, reactions, typing, read + delivered receipts, scroll-back.
- Unread indicators: drawer button + **dashboard event cards**. List pages (events/rehearsals/bookings index) are out of scope.
- Bell: **yes, mirror the push audience**, database-only (no email).
- Access layer: **dedicated session-auth web routes over the existing controllers + a shared presenter** (not stateful Sanctum on the API group, not Inertia-only).

## Current state (verified 2026-10-04)

- Backend is wire-complete and web-agnostic: `ConversationService`, `MessageFormatter`, `TopicUnreadService`, `ConversationPolicy`, `ConversationStreamEvent`, `routes/channels.php` (`conversation.{id}` authorised via policy `view`).
- Every chat route lives under `/api/mobile/*` behind `auth:sanctum`. The `api` middleware group has no `EnsureFrontendRequestsAreStateful`, so session cookies do not authenticate those routes.
- `summarize()`, `prefetchSummaryData()`, `topicType()`, `topicTitle()`, `threadPage()` are private helpers inside `App\Http\Controllers\Api\Mobile\ConversationsController`.
- Web has zero chat surface: no routes, no Vue components, no nav entry, no badges, no `private-conversation` subscriber. `resources/js/realtime/bandChannel.js` handles thin band signals only. `useBandRealtime(bandId, reloadMap)` is the page-level opt-in; no page maps the `message` model.
- Bell: database notifications rendered by `Layouts/Authenticated.vue`; each row links via `route(notification.data.route, notification.data.routeParams)` and shows `notification.data.text`. `TTSNotification::via()` adds `mail` when the user has `emailNotifications` on. No chat notification class exists.
- Web permissions page (`UserPermissionsController` → `Band/ShowPermissions`) enumerates `BandResource::cases()` only; `moderate:chat` is grantable from the mobile band-settings endpoint only.
- Web show controllers: `EventsController@show` → `Events/Show`, `RehearsalController@show` → `Rehearsals/RehearsalDetail`, `BookingsController@show` → `Bookings/Show` (layout `Bookings/Layout/BookingLayout.vue`, which derives its sub-nav from Ziggy GET routes containing `booking/`). Web `DashboardController@index` + `loadOlderEvents` build event rows via `UserEventsService::getEvents()` (array rows, incl. virtual rehearsal rows).
- PrimeVue 4 + Tailwind; `Drawer` is not yet registered in `resources/js/app.js`. Existing `Components/ImageLightbox.vue` can display attachments.

## Backend

### 1. `App\Services\Chat\ConversationPresenter` (new)

Move, without behaviour change:

| from `Api\Mobile\ConversationsController` | to presenter |
|---|---|
| `prefetchSummaryData($ids, User, $lastReads): array` | `prefetch(Collection $ids, User $user): array` (fetches lastReads itself) |
| `summarize(Conversation, User, array $prefetch): array` | `summarize(...)` |
| `topicType(Conversation): ?string` | `topicType(...)` |
| `topicTitle(Conversation): string` | `topicTitle(...)` |
| `threadPage(Request, Conversation, ?int $before): JsonResponse` | `threadPage(User $user, Conversation $c, ?int $before = null): array` (controller wraps in `response()->json`) |

Additions:

- `unreadCountFor(User $user, Model $target): ?int` — canonicalises via `ConversationService::canonicalTarget()` then delegates to `TopicUnreadService::unreadCountsForConversables()` for the single pair. Returns `null` when the user could not `view` the (existing or would-be) topic conversation, so pages can hide the button. Does **not** create a conversation.

The mobile controller keeps its public methods and delegates. The 15 existing `tests/Feature/Api/Mobile/Chat/*` suites must pass unchanged — that is the extraction's proof of byte-identical output.

### 2. `routes/chat.php` (new)

Loaded like the other route files. Group: `middleware(['auth', 'verified'])->name('chat.')`. All handlers are the existing mobile controller methods; they already use `$request->user()` + policies and contain nothing Sanctum-specific.

| Verb | URI | Handler | Name |
|---|---|---|---|
| GET | `chat/events/{event}/conversation` | `ConversationsController@forEvent` | `chat.events.conversation` |
| GET | `chat/rehearsals/{rehearsal}/conversation` | `ConversationsController@forRehearsal` | `chat.rehearsals.conversation` |
| GET | `bands/{band}/booking/{booking}/conversation` | `ConversationsController@forBooking` | `chat.bookings.conversation` — `middleware('booking.access')`, scoped bindings. **Excluded** from `BookingLayout`'s derived sub-nav (add to `excludeRoutes`). |
| GET | `chat/conversations/{conversation}/messages` | `ConversationsController@messages` | `chat.conversations.messages.index` |
| POST | `chat/conversations/{conversation}/messages` | `ConversationsController@storeMessage` | `chat.conversations.messages.store` |
| POST | `chat/conversations/{conversation}/read` | `ConversationsController@read` | `chat.conversations.read` |
| POST | `chat/conversations/{conversation}/typing` | `ConversationsController@typing` | `chat.conversations.typing` — `throttle:chat-typing` |
| PATCH | `chat/messages/{message}` | `MessagesController@update` | `chat.messages.update` |
| DELETE | `chat/messages/{message}` | `MessagesController@destroy` | `chat.messages.destroy` |
| GET | `chat/messages/{message}/attachments/{attachment}` | `MessagesController@attachment` | `chat.messages.attachments.show` |
| POST | `chat/messages/{message}/reactions` | `MessageReactionsController@store` | `chat.messages.reactions.store` |
| DELETE | `chat/messages/{message}/reactions/{emoji}` | `MessageReactionsController@destroy` | `chat.messages.reactions.destroy` |

Route-model binding keys match the mobile routes (`Events::getRouteKeyName()` is `key`, so `{event}` binds by key on both). Not registered in this slice: conversations index, `dm`, `contacts`, `delivered` (Messages slice).

The `ProcessChatMessagePush` job already runs on `storeMessage`, so web posts push to mobile with no extra wiring.

### 3. Inertia props

- `EventsController@show`, `RehearsalController@show`, `BookingsController@show` each add `'unreadCommentCount' => $presenter->unreadCountFor($user, $model)`. For events the target is the `Events` row (the presenter canonicalises rehearsal-backed events to the `Rehearsal`), so the event page and the rehearsal page agree.
- Web `DashboardController@index` and `loadOlderEvents` add `unread_comment_count` to each event row exactly as `Services\Mobile\DashboardFormatter` does for mobile (`conversablePairs()` → `TopicUnreadService` → merge; rows are arrays, use the existing `toRowArray` normalisation; virtual rehearsal rows get 0).

### 4. Bell notification — `App\Notifications\CommentPosted` (new)

- `via()` → `['database']` only. Deliberately not `TTSNotification`: that class also mails users with `emailNotifications`, and one email per comment is too noisy.
- `toArray()` returns the bell's existing shape:
  - `text`: `"{sender} commented on {topic title}: {snippet}"` (`📷 Photo` when body is empty; snippet truncated ~80 chars).
  - `route` / `routeParams`: the web show route for the canonical target — `events.show` `{key}`, `Booking Details` `{band, booking}`, `rehearsals.show` `{band, rehearsal_schedule, rehearsal}` — plus `comments: 1` so the page auto-opens the drawer (Ziggy appends unknown params as query string).
  - `conversation_id`, `message_id` for future "mark bell read when thread read" work (not in this slice).
- Dispatched from `ProcessChatMessagePush::handle()` in the same recipient loop, **topic conversations only**, author excluded. Audience is therefore identical to push by construction. DM/band-channel bell entries are a Messages-slice concern.

### 5. `moderate:chat` on the web permissions page

`UserPermissionsController@show` appends a `moderate:chat` entry (label "Moderate chat", description "Delete other members' comments and messages") to the permissions payload; `update` persists it the same way the mobile `BandSettingsController` does (team-scoped Spatie permission). `Band/ShowPermissions.vue` renders it as one extra row beneath the resource grid.

## Frontend

### 6. `resources/js/realtime/conversationChannel.js` (new)

`subscribeConversation(conversationId, handlers) → unsubscribe`. Refcounted per id like `bandChannel.js` so overlapping mounts cannot `Echo.leave()` a live channel. Binds the six wire events on `private-conversation.{id}`: `.message.created {message}`, `.message.updated {message}`, `.message.deleted {message_id}`, `.conversation.read {user_id,last_read_at}`, `.conversation.delivered {user_id,last_delivered_at}`, `.conversation.typing {user_id,name}`.

### 7. `resources/js/composables/useConversationThread.js` (new)

Comment-agnostic (reused by Messages).

State: `conversation` (summary incl. `can_moderate`), `messages` (oldest→newest), `participants`, `hasMore`, `loading`, `error`, `typingUsers`, `sending`.

Actions:
- `load(url)` — GET a ThreadPage (`{conversation, messages, participants, channel, has_more}`), then subscribe via the returned channel id.
- `loadOlder()` — GET messages `?before={oldest id}`; prepend; keeps scroll position (caller's job).
- `send({body, files})` — multipart POST; append the returned message; dedupes against the realtime echo by id.
- `edit(id, body)`, `remove(id)`, `toggleReaction(id, emoji)` (per-message in-flight guard, mirrors mobile's `_reactionsInFlight`).
- `markRead()` — debounced 1.5 s, POSTs `last_read_message_id` of the newest message.
- `ping typing` — throttled to one POST per 3 s while the composer has input.

Realtime handlers mirror the mobile `ChatThreadNotifier`: created → append if id unseen, trigger `markRead` when from another user; updated → replace; deleted → tombstone (`is_deleted`, body cleared); read/delivered → patch that participant; typing → add with a 5 s expiry, ignore own id. Unsubscribe on unmount.

### 8. Components — `resources/js/Components/Chat/`

- **`CommentsButton.vue`** — outlined PrimeVue `Button` with `pi pi-comments`, label "Comments", unread pill (hidden at 0). Props: `unreadCount`. Emits `open`. The host page passes the count prop and reloads it via realtime.
- **`CommentsDrawer.vue`** — PrimeVue `Drawer` (register in `app.js`), `position="right"`, ~`28rem` on `md+`, full width below. Header: topic title + close. Body: `ConversationThread`. Props: `visible`, `topicUrl`, `title`. On first open calls `load(topicUrl)` (resolve-or-create) and `markRead()`.
- **`ConversationThread.vue`** — scroll container; top sentinel triggers `loadOlder()` and preserves scroll offset; date separators (new day or >1 h gap, as `message_time.dart`); bubbles; typing line ("Alex is typing…"); footer "Seen by N" / DM "Delivered"/"Seen" — tap shows names in a popover. Auto-scrolls to bottom on own send and when already at bottom.
- **`MessageBubble.vue`** — author name, time on hover, body, attachment thumbnails (`chat.messages.attachments.show` URLs) opening `ImageLightbox`, reaction chips (own reactions highlighted, click toggles), "edited" marker, tombstone rendering. Hover/… menu: React, Edit (own, text-only messages), Delete (own, or `conversation.can_moderate`). Delete confirms via the existing `ConfirmDialog` service.
- **`MessageComposer.vue`** — autosizing `Textarea`; Enter sends, Shift+Enter newline; image picker + paste, up to 4 images (`image/jpeg`, `image/png`) with previews and remove; send disabled when empty; emits typing pings.
- **`ReactionPicker.vue`** — the same short tapback row mobile uses.

Dark mode via Tailwind `dark:` variants, matching the detail pages.

### 9. Page integration

| Page | Button placement | Topic URL | Realtime |
|---|---|---|---|
| `Events/Show.vue` | header row, next to "Setlist" | `route('chat.events.conversation', event.key)` | add `message: { props: ['unreadCommentCount'] }` to its `useBandRealtime` map |
| `Rehearsals/RehearsalDetail.vue` | header action row | `route('chat.rehearsals.conversation', rehearsal.id)` | same |
| `Bookings/Layout/BookingLayout.vue` | right end of the sub-nav row (visible on every booking tab) | `route('chat.bookings.conversation', {band, booking})` | layout-level `useBandRealtime(band.id, { message: { props: ['unreadCommentCount'] } })` |
| `Dashboard.vue` / `EventCard.vue` | unread pill on the card from `event.unread_comment_count` | — | add `message: ['events']` to the dashboard's existing reload map (coalesced) |

Drawer auto-opens when the page URL carries `comments=1` (bell deep link). Opening the drawer marks the thread read, which drops `unreadCommentCount` to 0 locally without waiting for a reload.

## Error handling

- Thread load failure → inline "Comments unavailable — retry" state inside the drawer (same four states as the mobile `CommentBar`: loading, empty "No comments yet", error, content).
- Send failure → the composer keeps the draft and shows a toast; nothing is appended optimistically for text (the response is awaited), so no rollback is needed. Images that fail validation surface the server message.
- Echo unavailable (no Pusher config in dev) → the thread still works via explicit actions; `subscribeConversation` is a no-op when `window.Echo` is absent, matching `bandChannel.js`.
- Policy 403 on resolve (e.g. a sub opening a booking page it cannot comment on) → the button is simply absent: the host page only renders `CommentsButton` when `unreadCommentCount !== null`; the presenter returns `null` when `$user->can('view')` would fail for the would-be conversation. (Bookings pages already exclude subs via `booking.access`.)

## Testing

**Laravel (`tests/Feature/Web/Chat/`)**
- Each web route: 200 for a session-authenticated member, 302→login when guest, 403 for a non-member and for a sub on a booking thread; `booking.access` on the booking resolve route; attachment route streams bytes for a viewer, 403 otherwise.
- Presenter extraction: all existing `tests/Feature/Api/Mobile/Chat/*` pass unchanged.
- Props: `unreadCommentCount` on all three pages; the rehearsal-backed event page and the rehearsal page report the same count; `null` when the viewer cannot view the thread.
- Dashboard: `unread_comment_count` on rows from `index` and `loadOlderEvents`; 0 on virtual rehearsal rows.
- `CommentPosted`: database-only; topic conversations only (none for DM/band); audience == push audience minus author; `routeParams` resolve through `route()` and include `comments => 1`.
- Permissions page: `moderate:chat` renders and round-trips.

**Vitest (`resources/js/tests/`)** — assert via props/DOM/listener spies, not `wrapper.vm`/`emitted()` (CI runs in production mode):
- `useConversationThread`: own-echo dedupe, tombstone, read/delivered patch, typing expiry, `loadOlder` ordering, reaction in-flight guard.
- `MessageComposer`: Enter sends, Shift+Enter does not, 4-image cap, disabled when empty.
- `CommentsButton`: pill hidden at 0, shown with count.
- `CommentsDrawer`: auto-open from `comments=1`.
- `conversationChannel`: refcounting + no-op without Echo (mirrors `bandChannel.test.js`).

**Browser verification** (Chrome DevTools against local Docker): post → arrives live in a second window; react; edit; delete + moderate; image upload + lightbox; dashboard pill; bell row deep-links with the drawer open.

## Rollout

One PR from `feat/web-comments` → `staging` (auto-deploys). No mobile change, no wire-contract change, no migration, so no ordering constraint with the app. Follow-up specs: Messages on web; mark-bell-read-on-thread-read; list-page badges if wanted.

## Out of scope (this slice)

Conversation list / DMs / band channel / contacts picker on web; nav "Messages" entry and global unread badge; bell entries for DMs and band channel; `delivered` bulk ack from web; email for comments; @mentions; list-page unread badges; avatars (backend returns `null` everywhere today).
