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
