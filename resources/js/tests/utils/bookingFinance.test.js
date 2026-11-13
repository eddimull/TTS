import { describe, expect, it } from 'vitest';
import { amountDue, sumAmountDue, unpaidAmount } from '@/utils/bookingFinance';

// $11,250 show, $11,200 paid, $50 written off: the backend reports 0 due.
const writtenOff = { price: '11250.00', amount_paid: '11200.00', amount_due: '0.00' };
const owing = { price: '1000.00', amount_paid: '400.00', amount_due: '600.00' };
const overpaid = { price: '1000.00', amount_paid: '1200.00', amount_due: '-200.00' };

describe('bookingFinance', () => {
  it('reads amount_due from the backend instead of price - amount_paid', () => {
    expect(amountDue(writtenOff)).toBe(0);
    expect(amountDue(owing)).toBe(600);
  });

  it('keeps overpaid bookings negative and treats missing values as 0', () => {
    expect(amountDue(overpaid)).toBe(-200);
    expect(amountDue({})).toBe(0);
    expect(amountDue(null)).toBe(0);
  });

  it('never counts a negative balance as unpaid', () => {
    expect(unpaidAmount(overpaid)).toBe(0);
    expect(unpaidAmount(writtenOff)).toBe(0);
    expect(unpaidAmount(owing)).toBe(600);
  });

  it('sums amount_due across bookings', () => {
    expect(sumAmountDue([writtenOff, owing])).toBe(600);
    expect(sumAmountDue([owing, overpaid])).toBe(400);
    expect(sumAmountDue(undefined)).toBe(0);
  });
});
