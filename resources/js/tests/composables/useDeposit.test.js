import { describe, it, expect } from 'vitest';
import { ref } from 'vue';
import { useDeposit } from '../../composables/useDeposit';

describe('useDeposit', () => {
  it('prefers backend expected_deposit_amount when present', () => {
    const booking = ref({
      price: '1000.00',
      deposit_type: 'percent',
      deposit_value: '50.00',
      expected_deposit_amount: '500.00',
    });
    const { depositAmount, remainingAmount } = useDeposit(booking);
    expect(depositAmount.value).toBe('500.00');
    expect(remainingAmount.value).toBe('500.00');
  });

  it('computes percent client-side when expected_deposit_amount missing', () => {
    const booking = ref({
      price: '1000.00',
      deposit_type: 'percent',
      deposit_value: '25.00',
    });
    const { depositAmount, remainingAmount } = useDeposit(booking);
    expect(depositAmount.value).toBe('250.00');
    expect(remainingAmount.value).toBe('750.00');
  });

  it('computes amount client-side when expected_deposit_amount missing', () => {
    const booking = ref({
      price: '1000.00',
      deposit_type: 'amount',
      deposit_value: '300.00',
    });
    const { depositAmount, remainingAmount } = useDeposit(booking);
    expect(depositAmount.value).toBe('300.00');
    expect(remainingAmount.value).toBe('700.00');
  });

  it('returns 0.00 when price is empty or zero', () => {
    const booking = ref({
      price: '0',
      deposit_type: 'percent',
      deposit_value: '50.00',
    });
    const { depositAmount, remainingAmount } = useDeposit(booking);
    expect(depositAmount.value).toBe('0.00');
    expect(remainingAmount.value).toBe('0.00');
  });

  it('reactively updates when booking ref changes', () => {
    const booking = ref({
      price: '1000.00',
      deposit_type: 'percent',
      deposit_value: '50.00',
    });
    const { depositAmount } = useDeposit(booking);
    expect(depositAmount.value).toBe('500.00');
    booking.value = { ...booking.value, deposit_value: '25.00' };
    expect(depositAmount.value).toBe('250.00');
  });
});
