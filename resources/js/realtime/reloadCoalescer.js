import { router } from '@inertiajs/vue3';

// One shared debounce window for every realtime-driven partial reload.
//
// Signals fan out to independent consumers (the page's useBandRealtime, the
// layout's notification bell) and a single user action broadcasts several
// queued signals whose arrival is spread by queue latency. Without a shared
// window each consumer fires its own router.reload back-to-back. Everything
// funnels through here so one burst becomes one reload with the union of
// requested props.
//
// The window is sized to absorb Horizon queue jitter between the several
// broadcasts of a single write, not just same-tick fan-out.
export const COALESCE_MS = 1000;

const pendingProps = new Set();
let callbacks = [];
let timer = null;

function flush() {
	timer = null;
	const only = [...pendingProps];
	const cbs = callbacks;
	pendingProps.clear();
	callbacks = [];
	if (!only.length) return;
	router.reload({
		only,
		onSuccess: () => cbs.forEach((fn) => fn()),
	});
}

export function queueReload(props, onSuccess) {
	props.forEach((p) => pendingProps.add(p));
	if (onSuccess) callbacks.push(onSuccess);
	if (!timer) timer = setTimeout(flush, COALESCE_MS);
}

/**
 * Withdraw props a consumer queued but no longer wants (page unmounting
 * mid-window). Props other consumers still care about must be re-queued by
 * them, so only pass props this consumer alone maps.
 */
export function cancelQueuedProps(props) {
	props.forEach((p) => pendingProps.delete(p));
}

/**
 * Reset module state (for testing only).
 */
export function __resetReloadCoalescer() {
	pendingProps.clear();
	callbacks = [];
	if (timer) clearTimeout(timer);
	timer = null;
}
