import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
	router: { reload: vi.fn() },
}));
import { router } from '@inertiajs/vue3';
import {
	COALESCE_MS,
	queueReload,
	cancelQueuedProps,
	__resetReloadCoalescer,
} from '../../realtime/reloadCoalescer';

describe('reloadCoalescer', () => {
	beforeEach(() => {
		vi.useFakeTimers();
		__resetReloadCoalescer();
		router.reload.mockClear();
	});
	afterEach(() => {
		vi.useRealTimers();
	});

	it('coalesces queues from independent consumers into one reload with the union of props', () => {
		queueReload(['bookings']);
		queueReload(['auth']);
		queueReload(['bookings', 'events']);
		expect(router.reload).not.toHaveBeenCalled();

		vi.advanceTimersByTime(COALESCE_MS);
		expect(router.reload).toHaveBeenCalledTimes(1);
		expect([...router.reload.mock.calls[0][0].only].sort()).toEqual(['auth', 'bookings', 'events']);
	});

	it('runs every queued onSuccess callback after the coalesced reload succeeds', () => {
		router.reload.mockImplementation((opts) => opts.onSuccess?.());
		const first = vi.fn();
		const second = vi.fn();

		queueReload(['auth'], first);
		queueReload(['bookings'], second);
		vi.advanceTimersByTime(COALESCE_MS);

		expect(first).toHaveBeenCalledTimes(1);
		expect(second).toHaveBeenCalledTimes(1);
	});

	it('starts a fresh window after a flush', () => {
		queueReload(['bookings']);
		vi.advanceTimersByTime(COALESCE_MS);
		queueReload(['events']);
		vi.advanceTimersByTime(COALESCE_MS);

		expect(router.reload).toHaveBeenCalledTimes(2);
		expect(router.reload.mock.calls[1][0].only).toEqual(['events']);
	});

	it('cancelQueuedProps drops pending props; an emptied window reloads nothing', () => {
		queueReload(['bookings', 'events']);
		cancelQueuedProps(['bookings', 'events']);
		vi.advanceTimersByTime(COALESCE_MS);

		expect(router.reload).not.toHaveBeenCalled();
	});

	it('cancelQueuedProps leaves other consumers props in place', () => {
		queueReload(['bookings']);
		queueReload(['auth']);
		cancelQueuedProps(['bookings']);
		vi.advanceTimersByTime(COALESCE_MS);

		expect(router.reload).toHaveBeenCalledTimes(1);
		expect(router.reload.mock.calls[0][0].only).toEqual(['auth']);
	});
});
