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
