import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { h } from 'vue';

global.route = vi.fn((name) => `/mock-route/${name}`);

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue');
  return {
    router: {
      get: vi.fn(),
      post: vi.fn(),
      visit: vi.fn(),
    },
    useForm: (initial) => {
      const form = reactive({ ...initial });
      form.post = vi.fn();
      form.put = vi.fn();
      form.delete = vi.fn();
      return form;
    },
  };
});

import BookingCreation from '../../Pages/Bookings/Components/BookingCreation.vue';

const stubs = {
  Input: { template: '<input />', props: ['modelValue', 'label', 'type', 'id', 'required'] },
  ContractOptions: { template: '<div />', props: ['modelValue'] },
  InputNumber: { template: '<input />', props: ['modelValue'] },
  LocationAutocomplete: { template: '<div />', props: ['modelValue', 'name', 'label', 'placeholder'] },
};

const DatePickerStub = {
  name: 'DatePicker',
  props: ['modelValue'],
  setup(_, { slots }) {
    return () => h('div', { class: 'stub-datepicker' }, [
      slots.date?.({ date: { day: 15, month: 4, year: 2026 } }),
    ]);
  },
};

const baseProps = (overrides = {}) => ({
  band: { id: 1, name: 'Test Band' },
  eventTypes: [{ id: 1, name: 'Wedding' }],
  bookedDates: [],
  bookingDetails: {},
  ...overrides,
});

const mountForm = (props) =>
  mount(BookingCreation, {
    props: baseProps(props),
    global: { stubs, components: { DatePicker: DatePickerStub } },
  });

describe('BookingCreation calendar status marks', () => {
  it('marks confirmed dates with strike-through styling', () => {
    const wrapper = mountForm({
      bookedDates: ['2026-05-15'],
      bookingDetails: {
        '2026-05-15': {
          name: 'Smith Wedding',
          event_type: 'Wedding',
          status: 'confirmed',
          start_time: '18:00',
        },
      },
    });

    const slot = wrapper.find('.stub-datepicker span');
    expect(slot.classes()).toContain('line-through');
    expect(slot.classes()).toContain('text-gray-400');
  });

  it('marks pending dates yellow', () => {
    const wrapper = mountForm({
      bookedDates: ['2026-05-15'],
      bookingDetails: {
        '2026-05-15': {
          name: 'Tentative Hold',
          event_type: 'Corporate',
          status: 'pending',
          start_time: '19:00',
        },
      },
    });

    const slot = wrapper.find('.stub-datepicker span');
    expect(slot.classes()).toContain('text-yellow-500');
  });

  it('marks draft dates blue', () => {
    const wrapper = mountForm({
      bookedDates: ['2026-05-15'],
      bookingDetails: {
        '2026-05-15': {
          name: 'Draft Booking',
          event_type: 'Private Party',
          status: 'draft',
          start_time: '20:00',
        },
      },
    });

    const slot = wrapper.find('.stub-datepicker span');
    expect(slot.classes()).toContain('text-blue-700');
  });

  it('applies no status class when the date has no booking', () => {
    const wrapper = mountForm({
      bookedDates: [],
      bookingDetails: {},
    });

    const slot = wrapper.find('.stub-datepicker span');
    expect(slot.classes()).not.toContain('line-through');
    expect(slot.classes()).not.toContain('text-yellow-500');
    expect(slot.classes()).not.toContain('text-blue-700');
  });
});
