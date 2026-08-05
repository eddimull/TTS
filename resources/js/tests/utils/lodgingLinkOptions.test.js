import { describe, it, expect } from 'vitest';
import { groupLinkOptions } from '@/utils/lodgingLinkOptions';

const opt = (id, name, date) => ({ id, name, date });

describe('groupLinkOptions', () => {
  const checkIn = '2030-06-10 15:00:00';
  const checkOut = '2030-06-13 11:00:00';

  it('groups by stay window, proximity, and the rest', () => {
    const groups = groupLinkOptions([
      opt(1, 'Far future', '2030-09-01'),
      opt(2, 'During', '2030-06-11'),
      opt(3, 'Near before', '2030-06-01'),
      opt(4, 'Undated', null),
    ], checkIn, checkOut);

    expect(groups.map(g => g.label)).toEqual(['During your stay', 'Nearby', 'Everything else']);
    expect(groups[0].options.map(o => o.id)).toEqual([2]);
    expect(groups[1].options.map(o => o.id)).toEqual([3]);
    expect(groups[2].options.map(o => o.id)).toEqual([1, 4]);
  });

  it('sorts nearby by distance from check-in', () => {
    const groups = groupLinkOptions([
      opt(1, 'Nine off', '2030-06-19'),
      opt(2, 'Two off', '2030-06-08'),
    ], checkIn, checkOut);
    expect(groups[0].label).toBe('Nearby');
    expect(groups[0].options.map(o => o.id)).toEqual([2, 1]);
  });

  it('without check-in: single group, date-ascending, undated last', () => {
    const groups = groupLinkOptions([
      opt(1, 'B', '2030-07-01'),
      opt(2, 'A', '2030-06-01'),
      opt(3, 'U', null),
    ], null, null);
    expect(groups).toHaveLength(1);
    expect(groups[0].options.map(o => o.id)).toEqual([2, 1, 3]);
  });
});
