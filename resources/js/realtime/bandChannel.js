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

/**
 * Reset the module state (for testing only).
 */
export function __resetBandChannelState() {
	channels.clear();
}
