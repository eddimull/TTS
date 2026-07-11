import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { installEchoMock } from '../mocks/echo';
import { BAND_EVENT, __resetBandChannelState } from '../../realtime/bandChannel';
import { COALESCE_MS, queueReload, __resetReloadCoalescer } from '../../realtime/reloadCoalescer';
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
		__resetBandChannelState();
		__resetReloadCoalescer();
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

		vi.advanceTimersByTime(COALESCE_MS);
		expect(router.reload).toHaveBeenCalledTimes(1);
		const only = router.reload.mock.calls[0][0].only;
		expect([...only].sort()).toEqual(['bookings', 'events']);
	});

	it('shares one reload window with other consumers (the layout bell)', () => {
		mountWith(1, { bookings: ['bookings'] });

		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 1, action: 'updated' });
		queueReload(['auth']);

		vi.advanceTimersByTime(COALESCE_MS);
		expect(router.reload).toHaveBeenCalledTimes(1);
		expect([...router.reload.mock.calls[0][0].only].sort()).toEqual(['auth', 'bookings']);
	});

	it('ignores models missing from the map', () => {
		mountWith(1, { bookings: ['bookings'] });
		echo.fire('band.1', BAND_EVENT, { model: 'roster', id: 1, action: 'updated' });
		vi.advanceTimersByTime(COALESCE_MS);
		expect(router.reload).not.toHaveBeenCalled();
	});

	it('honors a when() predicate (detail pages)', () => {
		mountWith(1, {
			bookings: { props: ['booking'], when: (p) => p.id === 42 },
		});

		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 7, action: 'updated' });
		vi.advanceTimersByTime(COALESCE_MS);
		expect(router.reload).not.toHaveBeenCalled();

		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 42, action: 'updated' });
		vi.advanceTimersByTime(COALESCE_MS);
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

		vi.advanceTimersByTime(COALESCE_MS);
		expect(router.reload).not.toHaveBeenCalled();
		expect(echo.leaveCalls).toEqual(['band.1']);
	});
});
