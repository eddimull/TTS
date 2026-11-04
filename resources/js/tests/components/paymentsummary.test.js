import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PaymentSummary from '@/Pages/Bookings/Components/PaymentSummary.vue';

const booking = (overrides = {}) => ({
  price: '1000.00',
  amountPaid: '0.00',
  amountLeft: '1000.00',
  deposit_type: 'percent',
  deposit_value: '25.00',
  expected_deposit_amount: '250.00',
  is_deposit_paid: false,
  deposit_due: '250.00',
  deposit_due_date: null,
  ...overrides,
});

const depositText = (props) =>
  mount(PaymentSummary, { props: { booking: booking(props) } })
    .find('[data-testid="deposit-summary"]');

const state = (overrides = {}) => ({
  owed: '250.00',
  due_date: '2026-11-25',
  status: 'upcoming',
  days_late: null,
  overdue_since: null,
  ...overrides,
});

const signed = (deposit, balance, booking = {}) => ({
  payment_due: {
    event_date: '2027-03-06',
    merged: false,
    deposit: state(deposit),
    balance: state({ owed: '750.00', due_date: '2027-02-27', ...balance }),
  },
  ...booking,
});

const render = (props) => mount(PaymentSummary, { props: { booking: booking(props) } });
const deposit = (props) => render(props).find('[data-testid="deposit-summary"]');
const balance = (props) => render(props).find('[data-testid="balance-status"]');

describe('PaymentSummary deposit', () => {
  it('shows the percent and resolved amount', () => {
    const text = deposit().text();
    expect(text).toContain('Deposit (25%)');
    expect(text).toContain('$250.00');
  });

  it('omits the percent for a flat deposit', () => {
    const text = deposit({
      deposit_type: 'amount',
      deposit_value: '300.00',
      expected_deposit_amount: '300.00',
    }).text();
    expect(text).not.toContain('%');
    expect(text).toContain('$300.00');
  });

  it('explains when the deposit is due on an unsigned contract', () => {
    expect(deposit().text()).toContain('Due 3 weeks after signing');
    expect(balance().exists()).toBe(false);
  });

  it('promises no date on a cancelled or signed-but-unconfirmed booking', () => {
    expect(deposit({ status: 'cancelled' }).text()).not.toContain('after signing');
    expect(deposit({ status: 'pending', contract: { status: 'completed' } }).text()).not.toContain('after signing');
  });

  it('is hidden on a booking with no contract', () => {
    expect(deposit({ contract_option: 'none' }).exists()).toBe(false);
  });

  it('shows what is left and when, once signed', () => {
    expect(deposit(signed({ owed: '150.00' })).text()).toContain('$150.00 still due by Nov 25, 2026');
  });

  it('flags an overdue deposit', () => {
    const el = deposit(signed({ status: 'overdue', overdue_since: '2026-10-12' }));
    expect(el.text()).toContain('$250.00 overdue since Oct 12, 2026');
    expect(el.find('p').classes()).toContain('bg-red-800');
  });

  it('reads as paid once payments cover the deposit', () => {
    const el = deposit(signed({ owed: '0.00' }));
    expect(el.text()).toContain('Deposit paid');
    expect(el.find('p').classes()).not.toContain('bg-red-800');
  });

  it('is hidden when there is no deposit to collect', () => {
    expect(deposit({ price: '0.00', expected_deposit_amount: '0.00' }).exists()).toBe(false);
    expect(deposit({ deposit_value: '0.00', expected_deposit_amount: '0.00' }).exists()).toBe(false);
  });
});

describe('PaymentSummary balance', () => {
  it('says the rest is due by its date while the deposit is unpaid', () => {
    expect(balance(signed()).text()).toBe('Rest due by Feb 27, 2027');
  });

  it('says due by its date once the deposit is paid', () => {
    expect(balance(signed({ owed: '0.00' })).text()).toBe('Due by Feb 27, 2027');
  });

  it('reads due now inside the last 7 days', () => {
    expect(balance(signed({ owed: '0.00' }, { status: 'due_now' })).text()).toBe('Due now, show is Mar 6, 2027');
  });

  it('flags overdue after the show', () => {
    const el = balance(signed({ owed: '0.00' }, { status: 'overdue', overdue_since: '2027-03-06' }));
    expect(el.text()).toBe('Overdue since the Mar 6, 2027 show');
    expect(el.classes()).toContain('bg-red-800');
  });

  it('is hidden when nothing is left', () => {
    expect(balance(signed({ owed: '0.00' }, { owed: '0.00' })).exists()).toBe(false);
  });
});
