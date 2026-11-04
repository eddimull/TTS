import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PaymentsDue from '@/Pages/Finances/Components/PaymentsDue.vue';

const row = (overrides = {}) => ({
  booking_id: 1,
  band_id: 9,
  booking_name: 'Hartley Wedding',
  type: 'deposit',
  status: 'overdue',
  amount: '2500.00',
  due_date: '2026-10-12',
  event_date: '2027-03-06',
  days_late: 23,
  overdue_since: '2026-10-12',
  deposit_type: 'percent',
  deposit_value: '50.00',
  deposit_amount: '2500.00',
  amount_paid: '0.00',
  ...overrides,
});

const band = (groups = {}) => {
  const all = { overdue: [], next_30: [], later: [], older: [], ...groups };
  const summary = {};
  Object.entries(all).forEach(([key, rows]) => {
    summary[key] = {
      count: rows.length,
      total: rows.reduce((sum, r) => sum + Number(r.amount), 0).toFixed(2),
    };
  });
  return { id: 9, name: 'Three Thirty Seven', groups: all, summary };
};

const route = (name, params) => `/bands/${params.band}/booking/${params.booking}/finances`;

const render = (bands) => mount(PaymentsDue, { props: { bands }, global: { mocks: { route } } });

describe('PaymentsDue', () => {
  it('shows an overdue deposit with how late it is and links to the booking', () => {
    const wrapper = render([band({ overdue: [row()] })]);
    const group = wrapper.find('[data-testid="group-overdue"]');

    expect(group.text()).toContain('Deposit (50%)');
    expect(group.text()).toContain('Oct 12, 2026 · 23 days late');
    expect(group.text()).toContain('$2,500.00');
    expect(group.find('a').attributes('href')).toBe('/bands/9/booking/1/finances');
  });

  it('reads a balance inside its 7 days as due now', () => {
    const wrapper = render([band({
      next_30: [row({ type: 'balance', status: 'due_now', days_late: null, overdue_since: null })],
    })]);

    expect(wrapper.find('[data-testid="group-next_30"]').text()).toContain('Due now');
  });

  it('shows part-paid deposits and flat deposits', () => {
    const wrapper = render([band({
      overdue: [
        row({ amount: '1250.00', amount_paid: '1750.00', deposit_amount: '3000.00' }),
        row({ booking_id: 2, deposit_type: 'amount', deposit_value: '300.00', amount: '300.00' }),
      ],
    })]);
    const text = wrapper.find('[data-testid="group-overdue"]').text();

    expect(text).toContain('$1,750.00 of $3,000.00 paid');
    expect(text).not.toContain('Deposit (300%)');
  });

  it('keeps Later collapsed until opened', async () => {
    const wrapper = render([band({ later: [row({ booking_name: 'Far Show', status: 'upcoming' })] })]);
    const group = wrapper.find('[data-testid="group-later"]');

    expect(group.text()).not.toContain('Far Show');
    await group.find('button').trigger('click');
    expect(group.text()).toContain('Far Show');
  });

  it('keeps Older collapsed until opened', async () => {
    const wrapper = render([band({ older: [row({ booking_name: 'Old Show' })] })]);
    const group = wrapper.find('[data-testid="group-older"]');

    expect(group.text()).not.toContain('Old Show');
    await group.find('button').trigger('click');
    expect(group.text()).toContain('Old Show');
  });

  it('says so when nothing is due', () => {
    expect(render([band()]).text()).toContain('No payments due');
  });

  it('names bands only when the owner has several', () => {
    expect(render([band({ overdue: [row()] })]).find('h2').exists()).toBe(false);
    const second = { ...band(), id: 10, name: 'Second Band' };
    expect(render([band({ overdue: [row()] }), second]).find('h2').text()).toBe('Three Thirty Seven');
  });
});
