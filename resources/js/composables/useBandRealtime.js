import { onBeforeUnmount, onMounted } from 'vue';
import { subscribeBandSignals } from '../realtime/bandChannel';
import { queueReload, cancelQueuedProps } from '../realtime/reloadCoalescer';

function normalize(entry) {
	return Array.isArray(entry) ? { props: entry, when: null } : entry;
}

/**
 * Page-level opt-in to band realtime signals: maps wire models to the
 * Inertia props to partial-reload. Reloads go through the shared
 * reloadCoalescer, so signals landing in the same window — including the
 * layout bell's auth refresh — coalesce into one router.reload with the
 * union of their props.
 *
 * useBandRealtime(band.id, { bookings: ['bookings'] })
 * useBandRealtime(band.id, { bookings: { props: ['booking'], when: p => p.id === booking.id } })
 */
export function useBandRealtime(bandIdOrIds, reloadMap = {}, options = {}) {
	let unsubscribe = null;

	function onSignal(payload) {
		options.onSignal?.(payload);

		const entry = reloadMap[payload.model];
		if (!entry) return;
		const { props, when } = normalize(entry);
		if (when && !when(payload)) return;

		queueReload(props);
	}

	onMounted(() => {
		unsubscribe = subscribeBandSignals(bandIdOrIds, onSignal);
	});

	onBeforeUnmount(() => {
		unsubscribe?.();
		cancelQueuedProps(
			Object.values(reloadMap).flatMap((entry) => normalize(entry).props),
		);
	});
}
