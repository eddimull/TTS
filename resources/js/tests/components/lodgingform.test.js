import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

global.route = vi.fn((name) => `/mock-route/${name}`);

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue');
  return {
    useForm: (initial) => {
      const form = reactive({ ...initial, errors: {}, processing: false });
      form.put = vi.fn(); form.post = vi.fn(); form.patch = vi.fn();
      form.delete = vi.fn(); form.reset = vi.fn(); form.clearErrors = vi.fn();
      return form;
    },
    Link: { template: '<a><slot /></a>' },
  };
});

import LodgingForm from '@/Pages/Lodging/Form.vue';

const band = { id: 1, name: 'Test Band' };

const mountOptions = {
  global: {
    stubs: {
      LocationAutocomplete: { template: '<input />' },
    },
  },
};

describe('Lodging Form', () => {
  it('renders create mode with an empty rooms list and an add-room button', () => {
    const wrapper = mount(LodgingForm, {
      props: { band, lodging: null, bookings: [], events: [] },
      ...mountOptions,
    });
    expect(wrapper.text()).toContain('Add room');
    expect(wrapper.text()).not.toContain('Confirmation');
  });

  it('adds a room row when Add room is clicked', async () => {
    const wrapper = mount(LodgingForm, {
      props: { band, lodging: null, bookings: [], events: [] },
      ...mountOptions,
    });
    await wrapper.find('[data-testid="add-room"]').trigger('click');
    expect(wrapper.findAll('[data-testid="room-row"]').length).toBe(1);
  });

  it('prefills fields in edit mode', () => {
    const wrapper = mount(LodgingForm, {
      props: {
        band,
        lodging: {
          id: 9, name: 'Hampton Inn', address: '123 Main St',
          check_in_at: '2030-01-10 15:00:00', check_out_at: '2030-01-12 11:00:00',
          notes: '', booking: null, event: null,
          rooms: [{ id: 1, label: 'King', confirmation_number: 'ABC', notes: '' }],
          attachments: [],
        },
        bookings: [], events: [],
      },
      ...mountOptions,
    });
    expect(wrapper.find('input#name').element.value).toBe('Hampton Inn');
    expect(wrapper.findAll('[data-testid="room-row"]').length).toBe(1);
  });
});
