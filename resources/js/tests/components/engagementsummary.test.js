import EngagementSummary from '@/Pages/Bookings/Components/EngagementSummary.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('EngagementSummary', () => {
  it('prefixes the date with the short weekday', () => {
    const wrapper = mount(EngagementSummary, {
      props: {
        booking: {
          start_date: '2026-10-15', // Thursday
          events: [{}],
        },
      },
    });
    // Bug context: booking date was rendering with no weekday and shifted off
    // by one in CST. Both must be fixed.
    expect(wrapper.text()).toContain('Thu 10/15/2026');
  });
});
