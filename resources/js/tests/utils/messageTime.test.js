import { describe, it, expect } from 'vitest';
import { DateTime } from 'luxon';
import { needsDateSeparator, dateSeparatorLabel, bubbleTimeLabel } from '../../utils/messageTime';

// Fixed clock: Thu 2026-03-05 15:00 local. Never use the real now() here.
const NOW = DateTime.fromISO('2026-03-05T15:00:00');

describe('messageTime', () => {
	it('needs a separator for the first message, a new day, or a > 1h gap', () => {
		expect(needsDateSeparator(null, '2026-03-05T10:00:00')).toBe(true);
		expect(needsDateSeparator('2026-03-04T23:50:00', '2026-03-05T00:10:00')).toBe(true);
		expect(needsDateSeparator('2026-03-05T10:00:00', '2026-03-05T11:30:00')).toBe(true);
		expect(needsDateSeparator('2026-03-05T10:00:00', '2026-03-05T10:45:00')).toBe(false);
	});

	it('labels today / yesterday / weekday-within-week / full date', () => {
		expect(dateSeparatorLabel('2026-03-05T09:05:00', NOW)).toBe('Today 9:05 AM');
		expect(dateSeparatorLabel('2026-03-04T21:30:00', NOW)).toBe('Yesterday 9:30 PM');
		expect(dateSeparatorLabel('2026-03-02T08:00:00', NOW)).toBe('Monday 8:00 AM');
		expect(dateSeparatorLabel('2026-02-20T08:00:00', NOW)).toBe('Feb 20, 2026 8:00 AM');
	});

	it('bubble time is hour:minute with meridiem', () => {
		expect(bubbleTimeLabel('2026-03-05T15:07:00')).toBe('3:07 PM');
	});
});
