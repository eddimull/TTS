import NavSubmenu from '@/Components/NavSubmenu.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

global.route = vi.fn((name, params) => `/mock-route/${name}`);

vi.mock('vuex', () => ({
  useStore: () => ({
    getters: {
      'eventTypes/getAllEventTypes': [
        { id: 7, name: 'Wedding' },
      ],
    },
  }),
}));

const stubs = {
  ResponsiveSubNav: { template: '<div><slot name="header" /></div>' },
  EngagementSummary: { template: '<div data-test="engagement-summary"></div>' },
};

const baseBooking = {
  id: 42,
  name: 'Wedding Reception',
  status: 'confirmed',
  event_type_id: 7,
  start_date: '2026-10-15',
  events: [{}],
};

function mountNav(booking = baseBooking) {
  return mount(NavSubmenu, {
    props: {
      routes: {},
      booking,
    },
    global: {
      stubs,
      config: { globalProperties: { route: global.route } },
    },
  });
}

describe('NavSubmenu header', () => {
  it('renders the booking title', () => {
    const wrapper = mountNav();
    expect(wrapper.text()).toContain('Wedding Reception');
  });

  it('renders the event-type tag', () => {
    const wrapper = mountNav();
    expect(wrapper.text()).toContain('Wedding');
  });

  it('renders a status pill with the booking status', () => {
    const wrapper = mountNav();
    const pill = wrapper.find('[data-test="status-pill"]');
    expect(pill.exists()).toBe(true);
    expect(pill.text().toLowerCase()).toContain('confirmed');
  });

  it('renders the engagement summary', () => {
    const wrapper = mountNav();
    expect(wrapper.find('[data-test="engagement-summary"]').exists()).toBe(true);
  });

  it('does not render the old "Status:" label line', () => {
    const wrapper = mountNav();
    // The previous header used the literal "Status: confirmed" inline label;
    // the new design surfaces status as a pill instead.
    expect(wrapper.text()).not.toMatch(/Status:\s/);
  });
});
