# Web Chat Dock Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Facebook-style chat dock on the web: a bottom-right launcher with the unread pill, a conversation-list popover, and up to three docked chat windows that survive Inertia navigation and page reloads.

**Architecture:** Pure frontend. A module-level `useChatDock` composable holds the dock state (windows, list rows, popover flag) and persists open windows to `localStorage`. `ChatDock.vue` is rendered as a sibling of Inertia's `App` root in `app.js`, so it is never remounted; it reads auth/page from `usePage()`, the unread count and realtime signal from the Vuex `user` module, and mounts the existing `ConversationThread` per open window. No routes, controllers or models change.

**Tech Stack:** Inertia + Vue 3 (`<script setup>`), Vuex, PrimeVue 4 + Tailwind (`darkMode: media`), axios, Ziggy `route()`, Vitest + @vue/test-utils (jsdom).

**Spec:** `docs/superpowers/specs/2026-10-06-web-chat-dock-design.md`

## Global Constraints

- Branch `feat/web-chat-dock` (off `origin/staging`); PR targets **staging**.
- No backend change. Mobile wire contract untouched.
- Vitest runs in production mode in CI: assert via DOM / props / listener spies only — never `wrapper.vm` or `wrapper.emitted()`. `.vue` files 2-space indent; `.js` files tabs.
- Dark mode: every colour class has its `dark:` pair from the spec §5 palette.
- `ChatDock` must never subscribe to Echo channels itself; realtime arrives via Vuex `chatSignal` (list) and `ConversationThread` (threads).
- Commit after every task; end commit messages with:
  ```
  Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01DRa1BRcHvMjSmmxvUZZeRy
  ```

---

## File structure

**Create** under `resources/js/`
- `composables/useChatDock.js` (+ `tests/composables/useChatDock.test.js`)
- `utils/conversationLabels.js`
- `Components/Chat/Dock/ChatDock.vue` (+ `tests/components/chatdock.test.js`)
- `Components/Chat/Dock/ChatDockLauncher.vue`
- `Components/Chat/Dock/ChatDockWindow.vue` (+ `tests/components/chatdockwindow.test.js`)

**Modify**
- `app.js` — render `ChatDock` beside `App`.
- `Components/Chat/ConversationThread.vue`, `Components/Chat/MessageComposer.vue` — `noun` prop (+ `tests/components/messagecomposer.test.js`).
- `Pages/Messages/Components/ConversationList.vue` — `showNew` prop.
- `Pages/Messages/Index.vue` — `noun="message"`, `conversationSubtitle`.

---

### Task 1: `noun` prop on thread + composer; `showNew` on the list; subtitle util

- [ ] **Step 1:** Add to `tests/components/messagecomposer.test.js` a test that `noun: 'message'` renders placeholder `Add a message…` and the default renders `Add a comment…`. Run → FAIL.
- [ ] **Step 2:** `MessageComposer.vue`: `noun: { type: String, default: 'comment' }`; placeholder and cap notice use it. `ConversationThread.vue`: same prop, `nouns` computed, all eight strings from spec §4, pass `:noun` down.
- [ ] **Step 3:** `ConversationList.vue`: `showNew` prop (default `true`) with `v-if` on the pencil button.
- [ ] **Step 4:** `utils/conversationLabels.js` exporting `conversationSubtitle(c)`; `Messages/Index.vue` uses it and passes `noun="message"`.
- [ ] **Step 5:** `npx vitest run resources/js/tests/components resources/js/tests/pages` → green. Commit: `feat(chat): noun prop for thread/composer copy, showNew on list, subtitle util`.

### Task 2: `useChatDock` composable

- [ ] **Step 1:** Write `tests/composables/useChatDock.test.js` against `createChatDock({ storage })` with an in-memory storage object and `vi.mock('axios')`: cap/oldest-drop, reopen un-minimises, close, toggleMinimize, persistence round-trip (`hydrate` restores), `refreshList` maps rows / drops stale windows once loaded / keeps rows on failure / posts delivered only when `listOpen`, `markRead`, `reset`. Run → FAIL.
- [ ] **Step 2:** Implement per spec §1 (`createChatDock`, default export `useChatDock()` singleton, `MAX_WINDOWS`, `windowRows` computed, `storageKey`).
- [ ] **Step 3:** Tests green. Commit: `feat(chat): useChatDock state composable with persistence`.

### Task 3: `ChatDockLauncher` + `ChatDockWindow`

- [ ] **Step 1:** `tests/components/chatdockwindow.test.js` (ConversationThread module-mocked like `messagesindex.test.js`): title + subtitle text; thread stub present when open with `data-url` = mocked route; absent when minimised; unread pill only when minimised and `unread_count > 0`; `onToggle`/`onClose` listener spies fire from their buttons; header click fires `onToggle`. Run → FAIL.
- [ ] **Step 2:** Implement both components per spec §2 / §5.
- [ ] **Step 3:** Tests green. Commit: `feat(chat): dock launcher and window components`.

### Task 4: `ChatDock` root + mount in `app.js`

- [ ] **Step 1:** `tests/components/chatdock.test.js` with mocks for `@inertiajs/vue3` (`usePage` returning a reactive page), `vuex` (`useStore` with reactive state + `dispatch` spy), `axios`, and module mocks for `ConversationList` (renders a button per row that calls `onSelect(id)`) and `ConversationThread`. Cases from spec §Tests. Run → FAIL.
- [ ] **Step 2:** Implement `ChatDock.vue` per spec §2 (teleport, `active` gate, `hidden md:flex`, hydrate/reset watch, `chatSignal` watch, outside-click/Escape close, windows + launcher column).
- [ ] **Step 3:** `app.js`: import `ChatDock`, `render: () => [h(App, props), h(ChatDock)]`.
- [ ] **Step 4:** `npm run test:pipeline` and `npm run build` green. Commit: `feat(chat): persistent chat dock mounted beside the Inertia root`.

### Task 5: Verification + PR

- [ ] **Step 1:** Browser pass per spec §Verification, light and dark.
- [ ] **Step 2:** PR to `staging` with the summary, screenshots, and the test commands run.
