import { describe, it, expect, beforeEach, vi } from 'vitest';
import { installEchoMock } from '../mocks/echo';
import { subscribeBandSignals, BAND_EVENT, __resetBandChannelState } from '../../realtime/bandChannel';

describe('subscribeBandSignals', () => {
	let echo;
	beforeEach(() => {
		__resetBandChannelState();
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

	it('same handler function subscribed twice delivers to both until each unsubscribes', () => {
		const seen = [];
		const shared = (p) => seen.push(p);
		const offA = subscribeBandSignals(1, shared);
		subscribeBandSignals(1, shared);

		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 1, action: 'updated' });
		expect(seen).toHaveLength(2); // both subscriptions live

		offA();
		echo.fire('band.1', BAND_EVENT, { model: 'bookings', id: 2, action: 'updated' });
		expect(seen).toHaveLength(3); // second subscription still delivers
		expect(echo.leaveCalls).toEqual([]);
	});

	it('pins the wire event name to the backend broadcastAs contract', () => {
		// Must match BandDataChanged::broadcastAs() = 'band.data-changed',
		// listened with Echo's leading-dot convention for custom names.
		expect(BAND_EVENT).toBe('.band.data-changed');
	});
});
