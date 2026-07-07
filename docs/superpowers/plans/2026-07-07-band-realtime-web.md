# Band Realtime Web Consumer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Web pages silently partial-reload their Inertia props when `band.data-changed` signals arrive, and the notifications bell updates live.

**Architecture:** A refcounted Echo channel module (`resources/js/realtime/bandChannel.js`) + a `useBandRealtime` composable that maps wire models → `router.reload({ only })`, opted into per page; `Authenticated.vue` wires an app-wide bell refresher off the new `auth.user.band_ids` shared prop.

**Tech Stack:** Laravel Inertia shared props (backend, one addition), Vue 3 + laravel-echo (window.Echo) + PrimeVue toast, vitest/jsdom.

**Spec:** `docs/superpowers/specs/2026-07-07-band-realtime-web-design.md`

## Global Constraints

- Repo `/home/eddie/github/TTS`, branch `feat/band-realtime-web` (stacked on `feat/band-realtime-broadcasts`). PR base **staging**, opened only AFTER TTS #516 merges.
- ALL php/artisan commands via `docker compose exec -T app <cmd>`. JS tests run on the HOST: `npx vitest run <path>`; full CI parity: `npm run test:pipeline`.
- Wire contract (fixed by the backend): event listened as **`.band.data-changed`** (leading dot — `broadcastAs` convention), channel `band.{bandId}` (Echo `private('band.' + id)` → wire `private-band.{id}`), payload `{model, id, action[, parent]}`, models `bookings|events|rehearsal|roster|event_member`.
- Debounce window: 300 ms. Echo errors: `console.warn` only — never toast from page consumers; the bell toast is the single loud surface.
- Vue: new files use `<script setup>`/composition style; Options-API pages being touched get a `setup()` block for the composable call — do not rewrite them.
- Vitest note: never assert on the `<!--v-if-->` comment marker (differs in CI); assert on text/finds.

---

### Task 1: `auth.user.band_ids` shared prop (backend)

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (web-user branch of `share()`)
- Test: `tests/Feature/InertiaSharedBandIdsTest.php` (create)

**Interfaces:**
- Produces: shared Inertia prop `auth.user.band_ids` — array of ints, deduped, owner+member+sub bands (`$webUser->allBands()->pluck('id')`). Absent for guests and contact-guard users. Consumed by Tasks 4-6 via `usePage().props.auth.user.band_ids`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/InertiaSharedBandIdsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InertiaSharedBandIdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_props_include_all_band_ids_owner_member_and_sub(): void
    {
        $user = User::factory()->create();
        $owned = Bands::factory()->create();
        $owned->owners()->create(['user_id' => $user->id]);
        $memberOf = Bands::factory()->create();
        $memberOf->members()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.band_ids', fn ($ids) => collect($ids)
                    ->sort()->values()->all() === collect([$owned->id, $memberOf->id])
                    ->sort()->values()->all()));
    }
}
```

Caveat check while writing: confirm `route('dashboard')` exists (it's the app's post-login landing route); if named differently, use the Bookings index route — the assertion only needs any Inertia page rendered through the web middleware.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=InertiaSharedBandIdsTest`
Expected: FAIL — `auth.user.band_ids` missing from the props.

- [ ] **Step 3: Implement**

In `app/Http/Middleware/HandleInertiaRequests.php`, in the **web** user array (the one already containing `id/name/email/navigation/notifications`), add one line:

```php
                    'band_ids' => $webUser->allBands()->pluck('id')->values()->all(),
```

(`allBands()` already exists on `User` — owner+member+sub, deduped by id. Do NOT touch the contact-guard branch.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=InertiaSharedBandIdsTest`
Expected: PASS

- [ ] **Step 5: Sanity-sweep pages that render through the middleware**

Run: `docker compose exec -T app php artisan test --filter=BookingLinkTest`
Expected: PASS (the existing shared-props assertions must be unaffected).

- [ ] **Step 6: Commit**

```bash
cd /home/eddie/github/TTS && git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/InertiaSharedBandIdsTest.php && git commit -m "feat(realtime-web): share auth.user.band_ids for layout-level channel subscriptions"
```

### Task 2: `bandChannel` core module (refcounted Echo subscriptions)

**Files:**
- Create: `resources/js/realtime/bandChannel.js`
- Create: `resources/js/tests/mocks/echo.js`
- Test: `resources/js/tests/realtime/bandChannel.test.js`

**Interfaces:**
- Produces:
  ```js
  export function subscribeBandSignals(bandIds, onSignal) // -> unsubscribe()
  // bandIds: int | int[] (nullish/empty → no-op, returns () => {})
  // onSignal(payload) per received `.band.data-changed`
  export const BAND_EVENT = '.band.data-changed'
  ```
  Channels are refcounted module-wide: two subscribers to band 7 share one Echo channel; `Echo.leave` fires only when the last unsubscribes. Task 3's composable and Task 4's bell both consume this.

- [ ] **Step 1: Write the Echo mock**

`resources/js/tests/mocks/echo.js`:

```js
// Minimal window.Echo stand-in: records private()/leave() calls and lets
// tests fire events into registered listeners.
export function installEchoMock() {
	const channels = new Map(); // name -> { listeners: Map(event -> [cb]) }

	const echo = {
		privateCalls: [],
		leaveCalls: [],
		private(name) {
			this.privateCalls.push(name);
			if (!channels.has(name)) channels.set(name, { listeners: new Map() });
			const entry = channels.get(name);
			const channel = {
				listen(event, cb) {
					if (!entry.listeners.has(event)) entry.listeners.set(event, []);
					entry.listeners.get(event).push(cb);
					return channel;
				},
				subscribed() { return channel; },
				error() { return channel; },
			};
			return channel;
		},
		leave(name) {
			this.leaveCalls.push(name);
			channels.delete(name);
		},
		fire(name, event, payload) {
			const entry = channels.get(name);
			(entry?.listeners.get(event) ?? []).forEach((cb) => cb(payload));
		},
	};

	window.Echo = echo;
	return echo;
}
```

- [ ] **Step 2: Write the failing tests**

`resources/js/tests/realtime/bandChannel.test.js`:

```js
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { installEchoMock } from '../mocks/echo';
import { subscribeBandSignals, BAND_EVENT } from '../../realtime/bandChannel';

describe('subscribeBandSignals', () => {
	let echo;
	beforeEach(() => {
		echo = installEchoMock();
	});

	it('subscribes each band channel and delivers signals', () => {
		const seen = [];
		subscribeBandSignals([1, 2], (p) => seen.push(p));

		expect(echo.privateCalls).toEqual(['band.1', 'band.2']);
		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 5, action: 'updated' });
		expect(seen).toEqual([{ model: 'bookings', id: 5, action: 'updated' }]);
	});

	it('accepts a single band id and no-ops on empty input', () => {
		subscribeBandSignals(7, () => {});
		expect(echo.privateCalls).toEqual(['band.7']);

		const off = subscribeBandSignals([], () => {});
		expect(typeof off).toBe('function');
		off(); // must not throw
	});

	it('refcounts: overlapping subscribers share a channel; leave only at zero', () => {
		const offA = subscribeBandSignals(1, () => {});
		const offB = subscribeBandSignals(1, () => {});
		expect(echo.privateCalls).toEqual(['band.1']); // one Echo subscription

		offA();
		expect(echo.leaveCalls).toEqual([]); // B still listening

		offB();
		expect(echo.leaveCalls).toEqual(['band.1']);
	});

	it('a resubscribe during overlap (Inertia page transition) keeps the channel live', () => {
		const offOldPage = subscribeBandSignals(1, () => {});
		const seen = [];
		subscribeBandSignals(1, (p) => seen.push(p)); // new page subscribes first
		offOldPage(); // old page unmounts after

		echo.fire('band.1', BAND_EVENT, { model: 'events', id: 9, action: 'created' });
		expect(echo.leaveCalls).toEqual([]);
		expect(seen).toHaveLength(1);
	});

	it('unsubscribe stops delivery to that subscriber only', () => {
		const a = [];
		const b = [];
		const offA = subscribeBandSignals(1, (p) => a.push(p));
		subscribeBandSignals(1, (p) => b.push(p));
		offA();

		echo.fire('band.1', BAND_EVENT, { model: 'roster', id: 3, action: 'deleted' });
		expect(a).toHaveLength(0);
		expect(b).toHaveLength(1);
	});

	it('is a no-op without window.Echo', () => {
		delete window.Echo;
		const off = subscribeBandSignals([1], () => {});
		off(); // must not throw
	});
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `npx vitest run resources/js/tests/realtime/bandChannel.test.js`
Expected: FAIL — module does not exist.

- [ ] **Step 4: Implement the module**

`resources/js/realtime/bandChannel.js`:

```js
// Refcounted subscriptions to the thin band-data-changed broadcast.
//
// Every consumer (page composable, layout bell) goes through here so that
// overlapping lifecycles — an Inertia page transition where the incoming
// page subscribes to band N before the outgoing page unmounts — can never
// Echo.leave() a channel someone still listens to.

export const BAND_EVENT = '.band.data-changed';

// bandId -> { count, handlers: Set<fn> }
const channels = new Map();

function channelName(bandId) {
	return `band.${bandId}`;
}

function ensureChannel(bandId) {
	let entry = channels.get(bandId);
	if (entry) return entry;

	entry = { count: 0, handlers: new Set() };
	channels.set(bandId, entry);

	window.Echo.private(channelName(bandId))
		.subscribed(() => {})
		.error((err) => {
			console.warn(`[bandChannel] auth/subscribe error on ${channelName(bandId)}`, err);
		})
		.listen(BAND_EVENT, (payload) => {
			entry.handlers.forEach((fn) => fn(payload));
		});

	return entry;
}

/**
 * Subscribe onSignal to one or more band channels.
 * Returns an unsubscribe function (idempotent).
 */
export function subscribeBandSignals(bandIds, onSignal) {
	const ids = (Array.isArray(bandIds) ? bandIds : [bandIds]).filter(
		(id) => id !== null && id !== undefined,
	);
	if (!ids.length || !window.Echo) return () => {};

	const entries = ids.map((id) => {
		const entry = ensureChannel(id);
		entry.count += 1;
		entry.handlers.add(onSignal);
		return { id, entry };
	});

	let done = false;
	return () => {
		if (done) return;
		done = true;
		entries.forEach(({ id, entry }) => {
			entry.handlers.delete(onSignal);
			entry.count -= 1;
			if (entry.count <= 0) {
				channels.delete(id);
				window.Echo?.leave(channelName(id));
			}
		});
	};
}
```

Note the deliberate handler-set semantics: one `onSignal` function per subscriber; `handlers.delete(onSignal)` on unsubscribe removes only that subscriber. (Same function object subscribed twice for the same band would collapse — consumers always pass fresh closures, and the tests pin the behaviors that matter.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `npx vitest run resources/js/tests/realtime/bandChannel.test.js`
Expected: PASS (6 tests)

- [ ] **Step 6: Commit**

```bash
cd /home/eddie/github/TTS && git add resources/js/realtime/bandChannel.js resources/js/tests/mocks/echo.js resources/js/tests/realtime/bandChannel.test.js && git commit -m "feat(realtime-web): refcounted band channel subscription module"
```

### Task 3: `useBandRealtime` composable (debounce → partial reload)

**Files:**
- Create: `resources/js/composables/useBandRealtime.js`
- Test: `resources/js/tests/composables/useBandRealtime.test.js`

**Interfaces:**
- Consumes: `subscribeBandSignals`, `BAND_EVENT` (Task 2); `router` from `@inertiajs/vue3`.
- Produces:
  ```js
  useBandRealtime(bandIdOrIds, reloadMap = {}, options = {})
  // reloadMap entry:  model: ['prop', ...]
  //               or  model: { props: ['prop'], when: (payload) => bool }
  // options.onSignal(payload): called (undebounced) for every signal —
  //   non-reload consumers. Reload behavior: matching signals are coalesced
  //   for 300ms, then ONE router.reload({ only: [union of props] }).
  ```
  Must be called during component setup (uses onMounted/onBeforeUnmount). Tasks 5-6 call this from pages; Task 4's bell uses subscribeBandSignals directly (Options-API layout).

- [ ] **Step 1: Write the failing tests**

`resources/js/tests/composables/useBandRealtime.test.js`:

```js
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { installEchoMock } from '../mocks/echo';
import { BAND_EVENT } from '../../realtime/bandChannel';
import { useBandRealtime } from '../../composables/useBandRealtime';

vi.mock('@inertiajs/vue3', () => ({
	router: { reload: vi.fn() },
}));
import { router } from '@inertiajs/vue3';

function mountWith(bandIds, reloadMap, options) {
	const Host = defineComponent({
		setup() {
			useBandRealtime(bandIds, reloadMap, options);
			return () => h('div');
		},
	});
	return mount(Host);
}

describe('useBandRealtime', () => {
	let echo;
	beforeEach(() => {
		vi.useFakeTimers();
		echo = installEchoMock();
		router.reload.mockClear();
	});
	afterEach(() => {
		vi.useRealTimers();
	});

	it('coalesces a burst into one partial reload with the union of props', () => {
		mountWith(1, { bookings: ['bookings'], events: ['events'] });

		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 1, action: 'updated' });
		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 2, action: 'updated' });
		echo.fire('band.1', BAND_EVENT, { model: 'events', id: 3, action: 'created' });
		expect(router.reload).not.toHaveBeenCalled();

		vi.advanceTimersByTime(300);
		expect(router.reload).toHaveBeenCalledTimes(1);
		const only = router.reload.mock.calls[0][0].only;
		expect([...only].sort()).toEqual(['bookings', 'events']);
	});

	it('ignores models missing from the map', () => {
		mountWith(1, { bookings: ['bookings'] });
		echo.fire('band.1', BAND_EVENT, { model: 'roster', id: 1, action: 'updated' });
		vi.advanceTimersByTime(300);
		expect(router.reload).not.toHaveBeenCalled();
	});

	it('honors a when() predicate (detail pages)', () => {
		mountWith(1, {
			bookings: { props: ['booking'], when: (p) => p.id === 42 },
		});

		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 7, action: 'updated' });
		vi.advanceTimersByTime(300);
		expect(router.reload).not.toHaveBeenCalled();

		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 42, action: 'updated' });
		vi.advanceTimersByTime(300);
		expect(router.reload).toHaveBeenCalledTimes(1);
		expect(router.reload.mock.calls[0][0].only).toEqual(['booking']);
	});

	it('calls options.onSignal for every signal, undebounced', () => {
		const onSignal = vi.fn();
		mountWith(1, {}, { onSignal });
		echo.fire('band.1', BAND_EVENT, { model: 'event_member', id: 1, action: 'created' });
		echo.fire('band.1', BAND_EVENT, { model: 'rehearsal', id: 2, action: 'deleted' });
		expect(onSignal).toHaveBeenCalledTimes(2);
	});

	it('unsubscribes and cancels pending reloads on unmount', () => {
		const wrapper = mountWith(1, { bookings: ['bookings'] });
		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 1, action: 'updated' });
		wrapper.unmount();

		vi.advanceTimersByTime(300);
		expect(router.reload).not.toHaveBeenCalled();
		expect(echo.leaveCalls).toEqual(['band.1']);
	});
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run resources/js/tests/composables/useBandRealtime.test.js`
Expected: FAIL — composable does not exist.

- [ ] **Step 3: Implement**

`resources/js/composables/useBandRealtime.js`:

```js
import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { subscribeBandSignals } from '../realtime/bandChannel';

const DEBOUNCE_MS = 300;

function normalize(entry) {
	return Array.isArray(entry) ? { props: entry, when: null } : entry;
}

/**
 * Page-level opt-in to band realtime signals: maps wire models to the
 * Inertia props to partial-reload. Signals arriving within the debounce
 * window coalesce into one router.reload with the union of their props.
 *
 * useBandRealtime(band.id, { bookings: ['bookings'] })
 * useBandRealtime(band.id, { bookings: { props: ['booking'], when: p => p.id === booking.id } })
 */
export function useBandRealtime(bandIdOrIds, reloadMap = {}, options = {}) {
	let unsubscribe = null;
	let timer = null;
	const pendingProps = new Set();

	function flush() {
		timer = null;
		const only = [...pendingProps];
		pendingProps.clear();
		if (only.length) router.reload({ only });
	}

	function onSignal(payload) {
		options.onSignal?.(payload);

		const entry = reloadMap[payload.model];
		if (!entry) return;
		const { props, when } = normalize(entry);
		if (when && !when(payload)) return;

		props.forEach((p) => pendingProps.add(p));
		if (!timer) timer = setTimeout(flush, DEBOUNCE_MS);
	}

	onMounted(() => {
		unsubscribe = subscribeBandSignals(bandIdOrIds, onSignal);
	});

	onBeforeUnmount(() => {
		unsubscribe?.();
		if (timer) clearTimeout(timer);
		pendingProps.clear();
	});
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run resources/js/tests/composables/useBandRealtime.test.js resources/js/tests/realtime/bandChannel.test.js`
Expected: PASS (11 tests)

- [ ] **Step 5: Commit**

```bash
cd /home/eddie/github/TTS && git add resources/js/composables/useBandRealtime.js resources/js/tests/composables/useBandRealtime.test.js && git commit -m "feat(realtime-web): useBandRealtime composable — debounced signal-to-partial-reload mapping"
```

### Task 4: Live notifications bell

**Files:**
- Create: `resources/js/realtime/bellRefresher.js`
- Modify: `resources/js/Layouts/Authenticated.vue` (`mounted`/`beforeUnmount`/`methods` — Options API, do not restructure)
- Test: `resources/js/tests/realtime/bellRefresher.test.js`

**Interfaces:**
- Consumes: `subscribeBandSignals` (Task 2), `auth.user.band_ids` (Task 1).
- Produces: `createBellRefresher({ reloadAuth, getUnseenCount, getLatest, toast })` → `{ onSignal, dispose }`. The layout wires: `subscribeBandSignals(band_ids, refresher.onSignal)`.

- [ ] **Step 1: Write the failing tests**

`resources/js/tests/realtime/bellRefresher.test.js`:

```js
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createBellRefresher } from '../../realtime/bellRefresher';

describe('createBellRefresher', () => {
	beforeEach(() => vi.useFakeTimers());
	afterEach(() => vi.useRealTimers());

	function make(counts) {
		let call = 0;
		const toast = vi.fn();
		const reloadAuth = vi.fn((opts) => opts.onSuccess?.());
		const refresher = createBellRefresher({
			reloadAuth,
			getUnseenCount: () => counts[Math.min(call++, counts.length - 1)],
			getLatest: () => ({ data: { text: 'Booking updated' } }),
			toast,
		});
		return { refresher, toast, reloadAuth };
	}

	it('debounces signals into one auth reload', () => {
		const { refresher, reloadAuth } = make([0, 0]);
		refresher.onSignal();
		refresher.onSignal();
		refresher.onSignal();
		expect(reloadAuth).not.toHaveBeenCalled();

		vi.advanceTimersByTime(300);
		expect(reloadAuth).toHaveBeenCalledTimes(1);
		expect(reloadAuth.mock.calls[0][0].only).toEqual(['auth']);
	});

	it('toasts when the unseen count increases across the reload', () => {
		// count read before reload: 1; after reload: 2
		const { refresher, toast } = make([1, 2]);
		refresher.onSignal();
		vi.advanceTimersByTime(300);
		expect(toast).toHaveBeenCalledTimes(1);
		expect(toast.mock.calls[0][0].detail).toBe('Booking updated');
	});

	it('stays silent when the count does not increase', () => {
		const { refresher, toast } = make([2, 2]);
		refresher.onSignal();
		vi.advanceTimersByTime(300);
		expect(toast).not.toHaveBeenCalled();
	});

	it('dispose cancels a pending refresh', () => {
		const { refresher, reloadAuth } = make([0, 0]);
		refresher.onSignal();
		refresher.dispose();
		vi.advanceTimersByTime(300);
		expect(reloadAuth).not.toHaveBeenCalled();
	});
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run resources/js/tests/realtime/bellRefresher.test.js`
Expected: FAIL — module does not exist.

- [ ] **Step 3: Implement the refresher**

`resources/js/realtime/bellRefresher.js`:

```js
const DEBOUNCE_MS = 300;

/**
 * Turns band signals into a live notifications bell: debounced reload of
 * the shared `auth` prop (which carries auth.user.notifications), and a
 * toast when the unseen count grew — the one loud surface of realtime.
 *
 * All effects are injected so the layout stays glue and this stays testable:
 *   reloadAuth({ only: ['auth'], onSuccess })  — Inertia partial reload
 *   getUnseenCount()                           — current unseen count
 *   getLatest()                                — newest notification object
 *   toast(options)                             — PrimeVue toast add()
 */
export function createBellRefresher({ reloadAuth, getUnseenCount, getLatest, toast }) {
	let timer = null;
	let disposed = false;

	function refresh() {
		timer = null;
		const before = getUnseenCount();
		reloadAuth({
			only: ['auth'],
			onSuccess: () => {
				if (disposed) return;
				const after = getUnseenCount();
				if (after > before) {
					const latest = getLatest();
					toast({
						severity: 'info',
						summary: 'New notification',
						detail: latest?.data?.text || 'Something changed in your band.',
						life: 6000,
					});
				}
			},
		});
	}

	return {
		onSignal() {
			if (disposed) return;
			if (!timer) timer = setTimeout(refresh, DEBOUNCE_MS);
		},
		dispose() {
			disposed = true;
			if (timer) clearTimeout(timer);
			timer = null;
		},
	};
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run resources/js/tests/realtime/bellRefresher.test.js`
Expected: PASS (4 tests)

- [ ] **Step 5: Wire the layout**

In `resources/js/Layouts/Authenticated.vue` (Options API — keep style):

Imports (script block top):

```js
import { router } from '@inertiajs/vue3';
import { subscribeBandSignals } from '@/realtime/bandChannel';
import { createBellRefresher } from '@/realtime/bellRefresher';
```

In `mounted()` after the existing `this.subscribeToUserChannel();`:

```js
        this.subscribeToBandSignals();
```

In `beforeUnmount()` (alongside the existing user-channel leave):

```js
        this._bandUnsubscribe?.();
        this._bellRefresher?.dispose();
```

New method in `methods` (next to `subscribeToUserChannel`):

```js
        subscribeToBandSignals() {
            const bandIds = this.$page.props.auth?.user?.band_ids;
            if (!bandIds?.length || !window.Echo) return;

            this._bellRefresher = createBellRefresher({
                reloadAuth: (opts) => router.reload(opts),
                getUnseenCount: () => this.unseenNotifications,
                getLatest: () => this.notifications?.[0],
                toast: (opts) => this.$toast?.add(opts),
            });
            this._bandUnsubscribe = subscribeBandSignals(bandIds, this._bellRefresher.onSignal);
        },
```

One wrinkle to handle while wiring: after `router.reload({ only: ['auth'] })` succeeds, the Vuex store must re-sync from the fresh page props — call `this.fetchNotifications()` inside `reloadAuth`'s `onSuccess` chain. Cleanest: wrap it —

```js
                reloadAuth: (opts) => router.reload({
                    ...opts,
                    onSuccess: () => {
                        this.fetchNotifications();
                        opts.onSuccess?.();
                    },
                }),
```

(`fetchNotifications` is already mapped via `mapActions("user", …)` in this component and reads `usePage().props.auth.user.notifications` — the exact prop the partial reload refreshes. Order matters: re-sync the store BEFORE the refresher's own `onSuccess` reads `getUnseenCount()`.)

- [ ] **Step 6: Full JS suite + manual smoke**

Run: `npm run test:pipeline`
Expected: all green (existing suite + 15 new tests).

- [ ] **Step 7: Commit**

```bash
cd /home/eddie/github/TTS && git add resources/js/realtime/bellRefresher.js resources/js/tests/realtime/bellRefresher.test.js resources/js/Layouts/Authenticated.vue && git commit -m "feat(realtime-web): live notifications bell — band signals refresh badge and toast new items"
```

### Task 5: Page opt-ins — bookings pages

**Files (modify only; one import + one composable call each):**
- `resources/js/Pages/Bookings/Index.vue` (Options API — add `setup()`)
- `resources/js/Pages/Bookings/Show.vue`
- `resources/js/Pages/Bookings/Contacts.vue`
- `resources/js/Pages/Bookings/Finances.vue`
- `resources/js/Pages/Bookings/Contract.vue`
- `resources/js/Pages/Bookings/Events.vue`
- `resources/js/Pages/Bookings/Lineup.vue`
- `resources/js/Pages/Bookings/Media.vue`
- `resources/js/Pages/Bookings/Payout.vue`
- `resources/js/Pages/Bookings/History.vue`

**Interfaces:**
- Consumes: `useBandRealtime` (Task 3), `auth.user.band_ids` (Task 1), each page's existing props.

- [ ] **Step 1: Bookings/Index.vue (Options API, multi-band)**

Add to its script imports:

```js
import { usePage } from '@inertiajs/vue3';
import { useBandRealtime } from '@/composables/useBandRealtime';
```

Add a `setup()` entry to the existing `export default {` object (before `props`):

```js
    setup() {
        useBandRealtime(usePage().props.auth?.user?.band_ids ?? [], {
            bookings: ['bookings'],
        });
    },
```

- [ ] **Step 2: Bookings/Show.vue (`<script setup>`)**

After the existing `defineProps` (which provides `booking`, `band`, `recentActivities`, …):

```js
import { useBandRealtime } from '@/composables/useBandRealtime';

useBandRealtime(props.band.id, {
    bookings: { props: ['booking', 'recentActivities'], when: (p) => p.id === props.booking.id },
    events: ['events'],
    event_member: ['events'],
});
```

(If the file destructures `defineProps` without assigning to `props`, assign it: `const props = defineProps({...})` — check each file and keep its style otherwise.)

- [ ] **Step 3: The eight sub-pages — same one-liner shape, page-specific maps**

Each `<script setup>` page gets the same import plus one call. Exact maps:

| File | call |
|---|---|
| Contacts.vue | `useBandRealtime(props.band.id, { bookings: { props: ['booking'], when: (p) => p.id === props.booking.id } })` |
| Finances.vue | `useBandRealtime(props.band.id, { bookings: { props: ['booking', 'payments'], when: (p) => p.id === props.booking.id } })` |
| Contract.vue | `useBandRealtime(props.band.id, { bookings: { props: ['booking'], when: (p) => p.id === props.booking.id } })` |
| Events.vue | `useBandRealtime(props.booking.band_id, { bookings: { props: ['booking'], when: (p) => p.id === props.booking.id }, events: ['events'], event_member: ['events'] })` |
| Lineup.vue | `useBandRealtime(props.band.id, { bookings: { props: ['booking'], when: (p) => p.id === props.booking.id }, events: ['events'], event_member: ['events'] })` |
| Media.vue | `useBandRealtime(props.band.id, { bookings: { props: ['booking'], when: (p) => p.id === props.booking.id } })` |
| Payout.vue | `useBandRealtime(props.band.id, { bookings: { props: ['booking', 'payoutResult', 'adjustedTotal'], when: (p) => p.id === props.booking.id }, events: ['events', 'payoutResult', 'adjustedTotal'], event_member: ['events', 'payoutResult', 'adjustedTotal'] })` |
| History.vue | `useBandRealtime(props.booking.band_id, { bookings: { props: ['booking'], when: (p) => p.id === props.booking.id } })` |

Per-file verification while editing: confirm the exact band-id source prop (`props.band.id` vs `props.booking.band_id` — the table above matches the current controllers; if a page lacks the named prop, use the other) and that every prop named in the map appears in that page's `defineProps`/`props`. Drop any prop the page doesn't receive. If a page is Options API (History/Lineup were unverified), use the Bookings/Index `setup()` shape instead.

- [ ] **Step 4: Analyze + suite**

Run: `npx vitest run resources/js/tests/ && npm run build 2>&1 | tail -5`
Expected: tests green; `vite build` completes (catches bad imports/syntax across the touched pages).

- [ ] **Step 5: Commit**

```bash
cd /home/eddie/github/TTS && git add resources/js/Pages/Bookings && git commit -m "feat(realtime-web): bookings pages live-reload on band signals"
```

### Task 6: Page opt-ins — dashboard, events, rehearsals, rosters, payout flow

**Files (modify):**
- `resources/js/Pages/Dashboard.vue`
- `resources/js/Pages/Events/Index.vue` (Options API — `setup()`)
- `resources/js/Pages/Events/Show.vue`
- `resources/js/Pages/Band/Rosters/Index.vue` (Options API — `setup()`)
- `resources/js/Pages/Finances/PayoutFlowEditor.vue`
- `resources/js/Pages/Rehearsals/Index.vue`
- `resources/js/Pages/Rehearsals/ScheduleDetail.vue`
- `resources/js/Pages/Rehearsals/RehearsalDetail.vue`

**Interfaces:** same as Task 5.

- [ ] **Step 1: Apply the calls**

| File | call |
|---|---|
| Dashboard.vue (`<script setup>`) | `useBandRealtime(usePage().props.auth?.user?.band_ids ?? [], { events: ['events'], event_member: ['events'], roster: ['events'], bookings: ['stats'] })` |
| Events/Index.vue (Options) | `setup() { useBandRealtime(usePage().props.auth?.user?.band_ids ?? [], { events: ['events'], event_member: ['events'], roster: ['events'] }); }` |
| Events/Show.vue | `useBandRealtime(props.band.id, { events: { props: ['event', 'userPayout'], when: (p) => p.id === props.event.id }, event_member: ['event', 'userPayout'] })` |
| Band/Rosters/Index.vue (Options) | `setup(props) { useBandRealtime(props.band.id, { roster: ['rosters', 'rosterMembers'] }); }` — Options `setup(props)` receives reactive props; `props.band.id` is available |
| Finances/PayoutFlowEditor.vue | `useBandRealtime(props.band.id, { roster: ['previewRosterMembers'] })` |
| Rehearsals/Index.vue | band prop is nullable (multi-band view): `useBandRealtime(props.band ? props.band.id : (props.bands ?? []).map((b) => b.id), { rehearsal: ['schedules'] })` |
| Rehearsals/ScheduleDetail.vue | `useBandRealtime(props.band.id, { rehearsal: ['schedule'] })` |
| Rehearsals/RehearsalDetail.vue | `useBandRealtime(props.band.id, { rehearsal: { props: ['rehearsal'], when: (p) => p.id === props.rehearsal.id } })` |

Same per-file verification as Task 5 (band-id source, props exist in `defineProps`, Options vs setup). Note `Rehearsals/RehearsalList` is a dead `Inertia::render` target with no Vue file — do NOT create it; mention it in the PR body as observed dead code.

Deliberate exclusions (record in the PR body, not code): create/edit FORM pages (`Rehearsals/ScheduleForm`, `Rehearsals/RehearsalForm`, and any booking create/edit forms) get NO opt-in — silently reloading the record under an open editor is exactly the clobber the silent-refresh decision accepted everywhere else because Inertia preserves form state; on a form page the props ARE the form's seed, so a reload mid-edit invites confusion for zero benefit.

- [ ] **Step 2: Full verification**

Run: `npm run test:pipeline && npm run build 2>&1 | tail -3`
Expected: all green, build completes.

Run: `docker compose exec -T app php artisan test`
Expected: backend unaffected (only the middleware changed, Task 1 tested it); known CalendarFeedTest parallel flake rule applies.

- [ ] **Step 3: Commit**

```bash
cd /home/eddie/github/TTS && git add resources/js/Pages && git commit -m "feat(realtime-web): dashboard, events, rehearsals, rosters, payout-flow live-reload on band signals"
```

### Task 7: Live browser verification + PR

**Files:** none new.

- [ ] **Step 1: End-to-end in a real browser**

With local `docker compose up` (Vite dev or fresh `npm run build`): log into `https://localhost:8710` (eddimull@gmail.com / password), open the Bookings index in one browser tab, then rename a band-1 booking via
`docker compose exec -T app php artisan tinker --execute='\App\Models\Bookings::find(621)->update(["name" => "WEB REALTIME TEST"]);'`
and confirm (a) the list updates without a manual refresh within ~2s, and (b) the notifications bell badge updates (the booking-updated job creates a TTSNotification). Rename back afterwards. Local broadcasting must be on real Pusher Cloud creds (already configured) — Echo web client uses `VITE_PUSHER_*` from `.env`; if the web client fails to connect, check `VITE_PUSHER_APP_KEY` matches the backend key and rebuild assets (VITE vars are baked at build time).

- [ ] **Step 2: Wait for #516, then push + PR**

TTS #516 (`feat/band-realtime-broadcasts`) must merge first — this branch is stacked on it. Then:

```bash
cd /home/eddie/github/TTS && git fetch origin staging && git rebase origin/staging && npm run test:pipeline && docker compose exec -T app php artisan test --filter=InertiaSharedBandIdsTest && git push -u origin feat/band-realtime-web && gh pr create --base staging --title "Realtime: web consumes band signals — live pages + notifications bell" --body "$(cat <<'EOF'
## Summary
- `auth.user.band_ids` joins Inertia shared props (owner+member+sub, deduped)
- Refcounted `bandChannel` module + `useBandRealtime` composable: thin `.band.data-changed` signals → debounced `router.reload({ only: [...] })` per page
- Live notifications bell: band signals refresh the badge/dropdown; toast when unseen count grows
- Opt-ins across bookings (index/show/8 sub-pages), dashboard, events, rehearsals, rosters, payout-flow editor
- First Echo tests in the web suite (mock + 15 tests)

Consumes the `BandDataChanged` broadcasts from #516. Web twin of tts_bandmate #94. Spec: `docs/superpowers/specs/2026-07-07-band-realtime-web-design.md`.

Observed dead code (not touched): `RehearsalController::index` renders `Rehearsals/RehearsalList`, which has no Vue file.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

https://claude.ai/code/session_01YNyzbE8jweH5FLjTMJt1kY
EOF
)"
```

- [ ] **Step 3: Wait for Copilot review and address its comments** (repo convention).
