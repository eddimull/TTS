# Web Messages (chat parity, slice 2) — Design

**Date:** 2026-10-05
**Status:** Approved (pending implementation)
**Repo:** TTS (Laravel + Inertia/Vue 3). No mobile change.
**Parent specs:** `tts_bandmate/docs/superpowers/specs/2026-07-12-comments-chat-design.md` (system design), `docs/superpowers/specs/2026-10-04-web-comments-design.md` (slice 1: web access layer + comments drawer, shipped in TTS PR #600).

## Goal

Bring the mobile Messages experience to the web: an inbox of every conversation the user can see (DMs, the band channel per band, topic threads with messages), a two-pane page to read and reply, starting DMs from a contact picker and from band member rows, a header icon with a live unread badge, and bell entries for direct messages.

This is slice 2 of the web parity programme. Leftovers (not here): Comments button on non-overview booking tabs; bell entries for band-channel messages; avatars.

## Decisions log

- Layout: **two-pane inbox** at `/messages/{conversation?}` (list left, thread right; stacked with a back control on narrow screens).
- Nav: **chat icon with unread badge next to the bell**, desktop and mobile headers. No nav-group item.
- Bell: **DMs yes, band channel no.**
- DM entry points: **Messages page picker + "Message" action on band member rows.**
- Data: **hybrid, server-seeded** — list as an Inertia prop, thread through the existing `ConversationThread`, list refreshes over axios on realtime signals, URL rewritten in place on selection.
- **Dark mode is a first-class requirement** (user request): every new surface is designed and verified in both schemes.

## Current state (after slice 1)

- `ConversationPresenter` (prefetch / summarize / topicType / topicTitle / threadPage / unreadCountFor) is shared by mobile and web. `Api\Mobile\ConversationsController::index()` still assembles the list itself (band channels + DMs + `visibleTopics()`), and `contacts()`, `storeDm()`, `delivered()` are mobile-route-only.
- `routes/chat.php` carries the 12 topic/message/reaction/attachment routes under `auth` + `verified`.
- Frontend: `realtime/conversationChannel.js`, `composables/useConversationThread.js`, `Components/Chat/{ConversationThread,CommentsDrawer,CommentsButton,MessageBubble,MessageComposer,ReactionPicker}.vue`, `composables/useCommentsDrawer.js`. `ConversationThread` takes `loadUrl` + `currentUserId` and emits `read`.
- Layout `Layouts/Authenticated.vue` (Options API + Vuex `userStore`): bell with `unseenNotifications` badge; `subscribeToUserChannel()` listens on `App.Models.User.{id}` for `.SetlistSessionStarted` only; `subscribeToBandSignals()` wires the bell refresher through `subscribeBandSignals`. `ConversationChanged` already broadcasts `user.data-changed` `{model:'message', id, action, parent:{model:'conversation', id}}` on that user channel for DMs; band/topic messages ride `band.data-changed` with model `message`.
- Dark mode: Tailwind default `darkMode: 'media'` — the app follows `prefers-color-scheme`; there is no in-app toggle. Existing conventions: `dark:bg-slate-700/800/900`, `dark:text-gray-50`, `dark:text-gray-400`, `dark:border-slate-600`.
- Band settings member list: `Pages/Band/Components/EditMembers.vue`, fed by `band.members_with_users[].user_data` (owner-only page).

## Backend

### 1. Presenter additions (`App\Services\Chat\ConversationPresenter`)

- `listFor(User $user): Collection` — moves the mobile index assembly: band channels for `$user->bands()` (lazily created via `ConversationService::bandChannelFor`), DMs the user participates in, and `visibleTopics` (topic conversations in any of `allBands()` that have ≥1 message incl. trashed, filtered by `can('view')`, conversable eager-loaded with the Rehearsal `events`/`rehearsalSchedule` morph loads). One `prefetch()` for all ids, `summarize()` per row, sorted by `last_message_at` desc. Returns the array rows (wire shape unchanged).
- `unreadTotalFor(User $user): int` — sum of `unread_count` over `listFor()`.
- `Api\Mobile\ConversationsController::index()` becomes `return response()->json(['conversations' => $this->presenter->listFor($request->user())]);` and `visibleTopics()` is deleted from the controller. `ConversationsIndexTest` + `ConversationsIndexTopicsTest` must pass unchanged.

### 2. Web routes (`routes/chat.php`, same `auth`+`verified` group)

| Verb | URI | Handler | Name |
|---|---|---|---|
| GET | `messages/{conversation?}` | `Web\MessagesController@index` | `messages.index` (also used as the deep-link target, param `conversation`) |
| GET | `chat/conversations` | `Api\Mobile\ConversationsController@index` | `chat.conversations.index` |
| POST | `chat/conversations/dm` | `Api\Mobile\ConversationsController@storeDm` | `chat.conversations.dm` |
| GET | `chat/contacts` | `Api\Mobile\ConversationsController@contacts` | `chat.contacts` |
| POST | `chat/conversations/delivered` | `Api\Mobile\ConversationsController@delivered` | `chat.conversations.delivered` |
| GET | `chat/unread-count` | `Web\MessagesController@unreadCount` | `chat.unread-count` → `{ count: int }` |

`App\Http\Controllers\Web\MessagesController` (new, thin): `index(Request, ?Conversation $conversation)` authorises `view` when an id is given (403 otherwise), then renders `Messages/Index` with `conversations => listFor(user)` and `initialConversationId => $conversation?->id`. `unreadCount()` returns `unreadTotalFor`.

### 3. Bell for DMs — `App\Notifications\DirectMessageReceived` (new)

Database-only (`via()` → `['database']`), constructed with `(Message, Conversation)`. `toArray()`: `text` = `"{sender}: {snippet}"` (`📷 Photo` when body empty, `Str::limit(…, 80)`), `route` = `messages.index`, `routeParams` = `['conversation' => id]`, plus `conversation_id`, `message_id`. Sent from `ProcessChatMessagePush` in the recipient loop when `$conversation->type === TYPE_DM` (so only the other participant receives it; the author is already excluded). Band-channel messages send nothing to the bell. `CommentPosted` behaviour unchanged.

## Frontend

### 4. Header icon + store

- `Store/userStore.js`: state `chatUnread: 0`, `chatSignal: 0`; actions `fetchChatUnread()` (GET `chat.unread-count`) and mutation `bumpChatSignal()`.
- `Layouts/Authenticated.vue`: a `Link` to `route('messages.index')` with `pi pi-comments` and a red count pill (`v-if="chatUnread > 0"`, same pill styling as the bell badge, hidden at 0), placed immediately left of the bell in both the desktop header and the responsive header. On mount: `fetchChatUnread()`. `subscribeToUserChannel()` additionally `.listen('.user.data-changed', p => { if (p.model === 'message') { bumpChatSignal(); fetchChatUnread(); } })`. The band-signal handler (`subscribeToBandSignals`) does the same when `payload.model === 'message'`. Debounce the fetch (300 ms) so a burst becomes one request. Only the layout subscribes to the user channel — pages never `Echo.leave()` it.

### 5. Messages page — `Pages/Messages/Index.vue` (+ components under `Pages/Messages/Components/`)

Props: `conversations: Array`, `initialConversationId: Number|null`. Local state: `rows` (seeded from the prop), `selectedId` (seeded from the prop), `mobileShowThread`.

- **`ConversationList.vue`** — search `InputText` (client-side filter on title/preview), "New message" `Button`, and rows. **`ConversationRow.vue`**: type icon (`pi pi-user` DM, `pi pi-users` band, `pi pi-briefcase` booking, `pi pi-calendar` event, `pi pi-headphones` rehearsal), title, one-line preview (`📷 Photo` when attachment-only, "No messages yet" when null), relative time via luxon `toRelative()`, unread pill hidden at 0, selected-row highlight. Emits `select(id)`.
- **Thread pane** — header with title + subtitle (`Direct message` / `Band channel` / `Event|Booking|Rehearsal thread`), then `<ConversationThread :load-url="route('chat.conversations.messages.index', selectedId)" :current-user-id="…" @read="onRead(selectedId)" />` keyed by `selectedId` so switching conversations remounts (unsubscribe/resubscribe). Empty state when nothing is selected: "Select a conversation".
- **Selection** — sets `selectedId`, `history.replaceState(null, '', route('messages.index', id))`; on narrow screens sets `mobileShowThread = true`; back control clears it. `popstate` is not handled (Inertia owns real navigation).
- **Refresh** — `refreshList()` = GET `chat.conversations.index`, replace `rows`, then POST `chat.conversations.delivered`. Called on mount (after seeding), on every `chatSignal` change (watch on the store), and after the thread emits `read` (which also sets that row's `unread_count` to 0 immediately and dispatches `fetchChatUnread`).
- **`NewMessageDialog.vue`** — PrimeVue `Dialog`; loads `chat.contacts` on open; filter by name; rows show name, `context`, and a `Sub` `Tag` when `is_sub`. Choosing one POSTs `chat.conversations.dm` `{user_id}` and emits `created(conversation)`; the page inserts/updates the row and selects it.
- **Responsive** — `md:grid md:grid-cols-[20rem_1fr]`; below `md` only one pane is visible at a time.

### 6. Member rows — `Pages/Band/Components/EditMembers.vue`

A `Message` button (`pi pi-comment`, text/outlined, `aria-label="Message {name}"`) on each member and owner row other than the current user: POST `chat.conversations.dm` then `router.visit(route('messages.index', conversation.id))`. Errors toast. The current user's own row shows no button.

### 7. Dark mode (requirement)

Every new element uses the existing palette pairs: surfaces `bg-white dark:bg-slate-800`, inset surfaces `bg-gray-50 dark:bg-slate-900`, borders `border-gray-200 dark:border-slate-600`, primary text `text-gray-900 dark:text-gray-50`, secondary `text-gray-500 dark:text-gray-400`, selected row `bg-blue-50 dark:bg-blue-900/40`, hover `hover:bg-gray-100 dark:hover:bg-slate-700`. PrimeVue `Dialog`/`InputText`/`Tag` inherit the Aura theme (already dark-aware). The badge pill is `bg-red-500 text-white` in both schemes (intentional). Verification is in both schemes (see Testing).

## Error handling

- List refresh failure: keep the current rows, toast once ("Could not refresh messages"), retry on the next signal.
- DM create failure (403 no shared band, network): toast with the server message; dialog stays open.
- Deep link to a conversation the viewer cannot view: 403 page from the policy (no partial render).
- Echo absent: page still works via explicit actions; signals simply never arrive.

## Testing

**Laravel** (`tests/Feature/Web/Chat/`)
- `ConversationPresenterListTest`: band channel lazily created and listed; DMs listed; topic threads only when they have messages; subs excluded from booking threads; sort by last message; `unreadTotalFor` sums.
- Mobile `ConversationsIndexTest`/`ConversationsIndexTopicsTest` unchanged and green (delegation proof).
- `MessagesPageTest`: `/messages` renders `Messages/Index` with `conversations` + null id; `/messages/{id}` sets `initialConversationId`; 403 for a non-viewable id; guest → login.
- `ChatWebRoutesTest` additions: list, dm (shared band required), contacts, delivered (204), unread-count `{count}`.
- `DirectMessageReceivedTest`: database-only; DMs only; recipient = other participant; route resolves to `/messages/{id}`; band-channel message sends nothing; `CommentPosted` still sent for topics.

**Vitest** (DOM/props/spies only; CI production mode)
- `ConversationRow`: icon per type, pill hidden at 0, preview fallbacks.
- `Messages/Index`: seeded rows render; click selects (thread `loadUrl` changes, URL rewritten via a spied `history.replaceState`); narrow-mode back control; store `chatSignal` change triggers the list fetch (axios mocked); `read` zeroes the row pill.
- Header badge: hidden at 0, shows count from the store.
- `NewMessageDialog`: filter narrows; choose → POST + `created` emitted.
- `EditMembers`: Message button absent on own row, present on others; click → POST + visit (router mocked).

**Browser** (two users, local Docker, Chrome DevTools MCP) — run the whole script twice: once in light, once with `prefers-color-scheme: dark` emulated:
open `/messages` → band channel + topic rows + unread pills; New message → pick a member → DM thread; reply from the other user → arrives live, header badge increments in the first tab; bell row for the DM deep-links to `/messages/{id}` with the thread open; "Message" on a member row lands in the DM; narrow viewport (≤ 640 px) stacks list/thread with a working back control. Screenshots for both schemes attached to the PR.

## Rollout

Branch `feat/web-messages` off `origin/staging` → one PR to staging (auto-deploys). No migration, no mobile change, wire contract untouched. Copilot review addressed before done.

## Out of scope

Band-channel bell entries; Comments button on non-overview booking tabs; avatars; muting; @mentions; marking bell rows read when the thread is read; a "Open event/booking" link from topic-thread headers (summary rows don't carry item keys yet).
