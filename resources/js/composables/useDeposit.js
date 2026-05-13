import { computed } from 'vue';

const fmt = (n) => Number(n).toFixed(2);

export function useDeposit(bookingRef) {
  const depositAmount = computed(() => {
    const b = bookingRef.value || {};
    const price = parseFloat(b.price) || 0;
    if (price <= 0) return '0.00';

    if (b.expected_deposit_amount !== undefined && b.expected_deposit_amount !== null) {
      return fmt(b.expected_deposit_amount);
    }
    const value = parseFloat(b.deposit_value) || 0;
    if (b.deposit_type === 'amount') {
      return fmt(value);
    }
    return fmt(price * (value / 100));
  });

  const remainingAmount = computed(() => {
    const b = bookingRef.value || {};
    const price = parseFloat(b.price) || 0;
    return fmt(price - parseFloat(depositAmount.value));
  });

  return { depositAmount, remainingAmount };
}
