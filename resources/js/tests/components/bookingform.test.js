import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

global.route = vi.fn((name, params) => `/mock-route/${name}`);

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue');
  return {
    router: {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
      visit: vi.fn(),
      on: vi.fn(() => () => {}),
    },
    useForm: (initial) => {
      const form = reactive({ ...initial });
      form.transform = () => form;
      form.put = vi.fn();
      form.post = vi.fn();
      form.delete = vi.fn();
      form.patch = vi.fn();
      return form;
    },
  };
});

vi.mock('vuex', () => ({
  useStore: () => ({
    getters: {
      'eventTypes/getAllEventTypes': [{ id: 1, name: 'Wedding' }],
    },
  }),
}));

import BookingForm from '../../Pages/Bookings/Components/BookingForm.vue';

const baseProps = (overrides = {}) => ({
  booking: {
    id: 1,
    name: 'Test',
    price: '1000.00',
    deposit_type: 'percent',
    deposit_value: '50.00',
    expected_deposit_amount: '500.00',
    contract_option: 'default',
    contract: null,
    event_type_id: 1,
    status: 'draft',
    notes: '',
    events: [],
    ...overrides.booking,
  },
  band: { id: 1, name: 'Test Band' },
  ...overrides,
});

const globalStubs = {
  stubs: {
    Container: { template: '<div><slot /></div>' },
    TextInput: { template: '<input :value="modelValue" />', props: ['modelValue', 'name', 'label'] },
    EventSubForm: { template: '<div />' },
    Button: { template: '<button><slot /></button>' },
  },
};

describe('BookingForm deposit', () => {
  it('renders the deposit field with computed counterpart', () => {
    const wrapper = mount(BookingForm, { props: baseProps(), global: globalStubs });
    const html = wrapper.html();
    expect(html).toContain('Deposit');
    expect(html).toMatch(/\$500\.00|= \$500/);
  });

  it('clears the deposit value when toggling modes', async () => {
    const wrapper = mount(BookingForm, { props: baseProps(), global: globalStubs });
    const depositInput = wrapper.find('[data-test="deposit-value-input"]');
    expect(depositInput.element.value).toBe('50.00');

    const amountToggle = wrapper.find('[data-test="deposit-mode-amount"]');
    await amountToggle.trigger('click');
    expect(depositInput.element.value).toBe('');
  });

  it('disables deposit inputs when contract is signed', () => {
    const wrapper = mount(BookingForm, {
      props: baseProps({ booking: { contract: { status: 'completed' } } }),
      global: globalStubs,
    });
    expect(wrapper.find('[data-test="deposit-value-input"]').attributes('disabled')).toBeDefined();
    expect(wrapper.find('[data-test="deposit-mode-percent"]').attributes('disabled')).toBeDefined();
    expect(wrapper.html()).toContain('Locked');
  });
});
