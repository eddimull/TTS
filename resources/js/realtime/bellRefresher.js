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
