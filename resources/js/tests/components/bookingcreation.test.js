import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { h } from 'vue';

global.route = vi.fn((name) => `/mock-route/${name}`);

const pageProps = vi.hoisted(() => ({ auth: { user: { owned_band_ids: [] } } }));

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue');
  return {
    usePage: () => ({ props: pageProps }),
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

// Mocked at the module level with a render function: name-based stubs and
// runtime-compiled templates don't apply under the production-mode pipeline.
vi.mock('@/Components/PriceEstimate/SuggestPriceButton.vue', async () => {
  const { h } = await import('vue');
  return {
    default: {
      name: 'SuggestPriceButton',
      props: ['bandId', 'payload'],
      emits: ['use-price'],
      setup(_, { emit }) {
        return () => h('button', {
          type: 'button',
          'data-test': 'suggest-price-button',
          onClick: () => emit('use-price', 3150),
        }, 'Suggest price');
      },
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

describe('BookingCreation suggest price', () => {
  it('hides the suggest price button for non-owners', () => {
    pageProps.auth.user.owned_band_ids = [];
    const wrapper = mountForm();
    expect(wrapper.find('[data-test="suggest-price-button"]').exists()).toBe(false);
  });

  it('shows the button for owners with a payload built from the form and applies the price', async () => {
    pageProps.auth.user.owned_band_ids = [1];
    const wrapper = mountForm();

    const button = wrapper.findComponent({ name: 'SuggestPriceButton' });
    expect(button.exists()).toBe(true);
    const payload = button.props('payload');
    expect(payload.start_time).toBe('19:00');
    expect(payload.duration_hours).toBe(4);
    expect(payload.date).toMatch(/^\d{4}-\d{2}-\d{2}$/);

    await wrapper.find('[data-test="suggest-price-button"]').trigger('click');
    expect(wrapper.vm.form.price).toBe(3150);
    pageProps.auth.user.owned_band_ids = [];
  });
});
