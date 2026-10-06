# Web Chat Dock (Facebook-style persistent chat) — Design

**Date:** 2026-10-06
**Status:** Approved (pending implementation)
**Repo:** TTS (Laravel + Inertia/Vue 3). No backend change. No mobile change.
**Parent specs:** `docs/superpowers/specs/2026-10-05-web-messages-design.md` (slice 2: Messages inbox, `ConversationThread`, Vuex `chatUnread`/`chatSignal`), `docs/superpowers/specs/2026-10-04-web-comments-design.md` (slice 1).

## Goal

Let a web user chat without leaving the page they are on: a floating launcher bottom-right on every authenticated page opens a conversation list, and picking a conversation opens a small chat window docked along the bottom of the viewport. Windows survive Inertia navigation and full page reloads. The existing Messages page stays as the full-screen view.

## Decisions log

- **Desktop only** (`md` and up). Below `md` the dock renders nothing; the header chat icon already links to `/messages`, which has a stacked mobile layout.
- **Hidden on the Messages page** (`Messages/Index`) so a thread is never on screen twice.
- **Mounted beside Inertia's root, not inside the layout.** 27 pages wrap `Authenticated.vue` in their own template, so the layout is destroyed on navigation between them. Dock state lives in a module-level composable; the component is a sibling of Inertia's `App` in `app.js` and is never remounted.
- **Max 3 open windows**; opening a fourth closes the oldest. Reopening a conversation that already has a window un-minimises it.
- **Minimised = not reading.** A minimised window unmounts its thread (no read acks while collapsed); its tab shows the conversation's `unread_count` from the list. Restoring remounts and reloads the thread (one GET for the latest page).
- **Persistence:** open window ids + minimised flags in `localStorage` per user; restored on load. Ids no longer present in the fetched list are dropped.
- **Data:** the dock fetches the list from `chat.conversations.index` on first mount and on every Vuex `chatSignal` bump (the layout already turns user-channel and band-channel `message` signals into that bump). Launcher pill = Vuex `chatUnread`. Threads use the unchanged `ConversationThread`.
- **Wording:** `ConversationThread` and `MessageComposer` gain a `noun` prop (`'comment'` default, `'message'`) so dock windows and the Messages page say "messages" while the comments drawer keeps "comments".
- **Dark mode is a requirement**, same palette as slice 2 (§7 of that spec).
- **Out of scope:** avatars, presence, starting a new DM from the dock (the list's "new" button is hidden; use the Messages page), notification sounds, drag-reordering, unread-in-tab-title.

## Current state (after slice 2)

- `ConversationThread.vue` (`loadUrl`, `currentUserId`, emits `read`) owns loading, paging, send/edit/delete/react, typing, read acks and the per-conversation Echo subscription via `useConversationThread`, which cleans up on the host component's unmount. Reused by `CommentsDrawer` and `Messages/Index`.
- `Pages/Messages/Components/ConversationList.vue` (search + rows, emits `select`/`new`) and `ConversationRow.vue` render the summary rows.
- Vuex `user` module: `chatUnread` (from `chat.unread-count`), `chatSignal` (bumped by `signalChatChange`, which also refetches the count). `Authenticated.vue` subscribes to `App.Models.User.{id}` and band channels and dispatches `signalChatChange` on `model === 'message'`; it `Echo.leave()`s the user channel on unmount and re-subscribes on mount, so pages that remount the layout briefly drop signals.
- `app.js`: `createApp({ render: () => h(App, props) })`, then plugins (Inertia, Ziggy, Vuex, PrimeVue + Toast + Confirm). `UploadQueueWidget` is a global floating widget inside the layout (`fixed bottom-4 right-4 z-50`, teleported to body).
- Toast group `br` is positioned bottom-right.

## Frontend

### 1. State — `composables/useChatDock.js`

Module-level singleton, built by an exported `createChatDock({ storage = window.localStorage } = {})` factory so tests can make fresh instances.

```js
state = reactive({
  userId: null,          // set by hydrate(); null = not authenticated / not ready
  listOpen: false,
  windows: [],           // [{ id, minimized }], oldest first, max 3
  conversations: [],     // summary rows from chat.conversations.index
  loaded: false,         // first successful fetch done
})
```

Actions:
- `hydrate(userId)` — sets `userId`, restores `windows` from `storage[key(userId)]` (`{ windows: [{id, minimized}] }`, ignore malformed), then `refreshList()`.
- `reset()` — clears everything (logout / user change).
- `refreshList()` — `axios.get(route('chat.conversations.index'))` → `conversations`; once `loaded`, drop windows whose id is not in the list. On failure keep current rows (no toast; the next signal retries). When the list popover is open, POST `chat.conversations.delivered` afterwards (same hook as the Messages page).
- `toggleList()` / `closeList()`.
- `open(id)` — existing window → `minimized = false`; else push `{id, minimized:false}`, shifting the oldest while `length > 3`. Closes the list popover. Persists.
- `close(id)`, `toggleMinimize(id)` — persist.
- `markRead(id)` — zero that row's `unread_count` (the caller dispatches `user/fetchChatUnread`).
- Getters: `windowRows` (windows joined to their summary; a window whose row is missing before `loaded` renders with title "Conversation" until the fetch lands), `MAX_WINDOWS = 3`.

Persistence key: `tts.chatDock.v1.{userId}`. Only `windows` is stored.

### 2. Components — `Components/Chat/Dock/`

**`ChatDock.vue`** — root, rendered by `app.js`. `<Teleport to="body">`.
- Reads `usePage()`; `active = auth.user?.id && auth.user.type !== 'contact' && page.component !== 'Messages/Index'`. Renders nothing when inactive (`v-if`). Below `md` the root is `hidden md:flex`.
- `watch(userId, immediate)` → `hydrate(id)` or `reset()` when it becomes null.
- `watch(store.state.user.chatSignal)` → `refreshList()`.
- Layout: `fixed bottom-0 right-4 z-40 flex items-end gap-3 pointer-events-none` (children re-enable pointer events). Order left→right: windows (oldest first), then the launcher column. `z-40` sits under `UploadQueueWidget` (`z-50`) and PrimeVue overlays, above page content.
- Launcher column: `ChatDockLauncher` + the list popover above it when `listOpen` (`w-80 h-[28rem]`, card styling, contains `ConversationList` with `:show-new="false"`, `@select="open"`). `Escape` closes the popover; clicking outside closes it (document `mousedown` listener while open).
- Windows: `<ChatDockWindow v-for="w in windowRows" :key="w.id" …>` with `@close`, `@toggle`, `@read="onRead(w.id)"` where `onRead` = `dock.markRead(id)` + `store.dispatch('user/fetchChatUnread')`.

**`ChatDockLauncher.vue`** — round 48px button (`pi pi-comments`), `aria-label` "Chat" / "Chat, N unread", red pill `data-test="dock-unread-pill"` when `count > 0`, `aria-expanded`. Props `count`, `open`. Emits `click`.

**`ChatDockWindow.vue`** — props `conversation` (summary row), `minimized`, `currentUserId`. Emits `close`, `toggle`, `read`.
- Shell: `w-80 pointer-events-auto rounded-t-lg shadow-2xl border border-b-0 border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 flex flex-col`; `h-[28rem]` when open, header-only when minimised.
- Header (`button`-like, click toggles): type icon (same map as `ConversationRow`), truncated title, subtitle (`Direct message` / `Band channel` / `Booking|Event|Rehearsal thread`), unread pill `data-test="window-unread-pill"` when minimised and `unread_count > 0`, minimise (`pi pi-minus`/`pi pi-window-maximize`) and close (`pi pi-times`) buttons with `@click.stop`.
- Body (`v-if="!minimized"`): `<ConversationThread :load-url="route('chat.conversations.messages.index', conversation.id)" :current-user-id noun="message" class="flex-1 min-h-0" @read="$emit('read')" />`.

Subtitle logic is extracted from `Messages/Index.vue` into `utils/conversationLabels.js` (`conversationSubtitle(c)`), used by both.

### 3. Mount — `app.js`

```js
const app = createApp({ render: () => [h(App, props), h(ChatDock)] });
```
`ChatDock` is imported at the top of `app.js`. Nothing else changes; the dock uses `usePage()` (module-level page state), `useStore()` (provided app-wide), the `route` mixin/ziggy import, and PrimeVue's Toast/Confirm services (the `<Toast>`/`<ConfirmDialog>` outlets live in the layout, present on every authenticated page).

### 4. Existing component changes

- `ConversationThread.vue`: `noun: { type: String, default: 'comment' }` → `nouns = computed(() => ({ one: noun, many: noun + 's' }))` used in "Loading {many}…", "{Many} unavailable.", "Load earlier {many}", "No {many} yet. Start the conversation.", "Editing {one}", toasts "Could not load earlier {many}", "Could not send your {one}…", confirm "Delete this {one}?" / "Delete {one}". Passes `:noun` to `MessageComposer`.
- `MessageComposer.vue`: `noun` prop → placeholder "Add a {one}…", cap notice "Up to N images per {one}."
- `ConversationList.vue`: `showNew: { type: Boolean, default: true }` gates the pencil button.
- `Messages/Index.vue`: pass `noun="message"`; use `conversationSubtitle`.

### 5. Visual spec

- Launcher: `w-12 h-12 rounded-full bg-blue-600 hover:bg-blue-700 text-white shadow-lg`, pill as `MessagesNavIcon` (`bg-red-500 text-white text-[10px]`).
- Popover/list card and windows: `bg-white dark:bg-slate-800`, borders `border-gray-200 dark:border-slate-600`, header `bg-gray-50 dark:bg-slate-900`, text `text-gray-900 dark:text-gray-50` / `text-gray-500 dark:text-gray-400`, hover `hover:bg-gray-100 dark:hover:bg-slate-700`.
- Known overlaps, accepted: toast group `br` and `UploadQueueWidget` share the bottom-right corner; both are transient and sit above the dock.

## Realtime & read semantics

- New message in an open (non-minimised) window: the thread's own channel subscription appends it and acks read when appropriate (unchanged behaviour). The layout's signal also bumps `chatSignal`, so the list refreshes and the launcher pill updates via `fetchChatUnread`.
- New message for a minimised or unopened conversation: list refresh updates `unread_count` → tab pill / list row; launcher pill via the store.
- Opening/restoring a window mounts the thread, which calls `markRead()` after load → `read` → row zeroed + `fetchChatUnread` → header icon and launcher pills drop together.
- Signal gap on layout remount: at worst the list is one refresh behind until the next signal; opening the popover does not force a refetch (the mount fetch + signals are enough; keep it simple).

## Tests (Vitest, production-mode rules: DOM / props / listener spies only)

- `tests/composables/useChatDock.test.js` — cap at 3 drops oldest; reopen un-minimises without duplicating; close/toggleMinimize; persistence round-trip through an in-memory storage; `refreshList` maps rows and drops stale windows once loaded, keeps rows on failure; `markRead` zeroes a row; `reset` clears.
- `tests/components/chatdock.test.js` — renders nothing for contact user / no user / `Messages/Index`; launcher pill mirrors `chatUnread`; clicking the launcher shows the list (ConversationList stubbed via module mock) and selecting opens a window; windows get the right `loadUrl`; `chatSignal` bump calls `axios.get`; `read` from a window dispatches `user/fetchChatUnread`.
- `tests/components/chatdockwindow.test.js` — title/subtitle, thread stub present when open and absent when minimised, unread pill only when minimised, minimise/close listeners.
- `tests/components/messagecomposer.test.js` — `noun="message"` placeholder.
- Existing suites unchanged and green.

## Verification

`npm run test:pipeline`, `npm run build`. Browser: open a booking page → launcher visible → open list → open two conversations → navigate to Rehearsals (a layout-remounting page) and to a booking tab → windows persist → send/receive a message from a second account → pills update → minimise/restore → reload the page → windows restored → `/messages` → dock hidden → resize below `md` → dock hidden. Light and dark.
