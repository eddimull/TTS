import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import DepositLine from '../../Pages/Contact/Components/DepositLine.vue';

describe('DepositLine', () => {
  it('renders nothing when depositDueDate is null', () => {
    const wrapper = mount(DepositLine, {
      props: { amount: '500.00', isDepositPaid: false, depositDueDate: null },
    });
    expect(wrapper.html().trim()).toBe('<!--v-if-->');
  });

  it('renders amount and due date when unpaid', () => {
    const wrapper = mount(DepositLine, {
      props: { amount: '500.00', isDepositPaid: false, depositDueDate: 'Jun 3, 2026' },
    });
    const html = wrapper.html();
    expect(html).toContain('$500.00');
    expect(html).toContain('Jun 3, 2026');
  });

  it('renders "Deposit paid" when paid', () => {
    const wrapper = mount(DepositLine, {
      props: { amount: '500.00', isDepositPaid: true, depositDueDate: 'Jun 3, 2026' },
    });
    expect(wrapper.html()).toContain('Deposit paid');
  });
});
