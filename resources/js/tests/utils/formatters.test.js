import { describe, expect, it } from 'vitest';
import { formatDate, formatDateRange } from '@/utils/formatters';

describe('formatters', () => {
  describe('formatDate', () => {
    it('renders an ISO date-only string as the same calendar day in any timezone', () => {
      // Bug: `new Date('2026-10-15')` parses as UTC midnight, then renders as
      // 10/14/2026 in any timezone west of UTC. Date-only strings must be
      // treated as local dates so the calendar day never shifts.
      expect(formatDate('2026-10-15')).toBe('10/15/2026');
    });

    it('formats a Date object', () => {
      const d = new Date(2026, 9, 15); // local-time Oct 15, 2026
      expect(formatDate(d)).toBe('10/15/2026');
    });
  });

  describe('formatDateRange', () => {
    it('does not shift a single-day range off by one for date-only strings', () => {
      expect(formatDateRange('2026-10-15', '2026-10-15')).toBe('10/15/2026');
    });
  });
});
