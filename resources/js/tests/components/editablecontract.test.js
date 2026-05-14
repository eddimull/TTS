import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EditableContractWYSIWYG from '../../Pages/Bookings/Components/EditableContractWYSIWYG.vue';

const baseBand = { name: 'Test Band', address: '123 Main', city: 'NOLA', state: 'LA', zip: '70112' };
const baseBooking = (overrides = {}) => ({
  price: '1000.00',
  deposit_type: 'percent',
  deposit_value: '50.00',
  expected_deposit_amount: '500.00',
  name: 'Test',
  contacts: [{ name: 'Jane Buyer', email: 'jane@example.com' }],
  contract: null,
  is_multi_event: false,
  events: [],
  ...overrides,
});

const globalStubs = {
  stubs: {
    Toolbar: { template: '<div><slot name="start" /><slot name="end" /></div>' },
    Button: { template: '<button><slot /></button>' },
    InputText: { template: '<input />' },
    Textarea: { template: '<textarea />' },
    draggable: { template: '<div><slot name="item" :element="{}" /></div>' },
  },
};

describe('EditableContractWYSIWYG deposit', () => {
  it('renders the resolved deposit and remaining-balance numbers', () => {
    const wrapper = mount(EditableContractWYSIWYG, {
      props: { booking: baseBooking(), band: baseBand, initialTerms: [] },
      global: globalStubs,
    });
    const html = wrapper.html();
    expect(html).toContain('$500.00');
    // Both deposit ($500) and remaining ($500) should be present
    expect(html.match(/\$500\.00/g).length).toBeGreaterThanOrEqual(2);
  });

  it('reflects amount mode correctly', () => {
    const wrapper = mount(EditableContractWYSIWYG, {
      props: {
        booking: baseBooking({
          deposit_type: 'amount',
          deposit_value: '300.00',
          expected_deposit_amount: '300.00',
        }),
        band: baseBand,
        initialTerms: [],
      },
      global: globalStubs,
    });
    const html = wrapper.html();
    expect(html).toContain('$300.00');
    expect(html).toContain('$700.00');
  });
});
