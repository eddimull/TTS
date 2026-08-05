import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Body from '../../Components/Event/Card/Body.vue';

global.route = vi.fn((name, id) => `/mock/${name}/${id}`);

const baseEvent = {
  id: 1, title: 'Gig', date: '2030-05-01',
  lodgings_summary: [
    { id: 9, name: 'Dash Hotel', address: null, check_in_at: '2030-04-30 15:00:00', check_out_at: '2030-05-02 11:00:00', room_count: 2 },
  ],
};

const globalConfig = {
  config: { globalProperties: { route: global.route } },
};

describe('EventCard Body lodging line', () => {
  it('renders hotel name and check-in time', () => {
    const wrapper = mount(Body, { props: { event: baseEvent, type: 'event' }, global: globalConfig });
    expect(wrapper.text()).toContain('Dash Hotel');
    expect(wrapper.text()).toContain('3:00 PM');
  });

  it('renders nothing without lodging', () => {
    const wrapper = mount(Body, { props: { event: { ...baseEvent, lodgings_summary: [] }, type: 'event' }, global: globalConfig });
    expect(wrapper.text()).not.toContain('Dash Hotel');
  });
});
