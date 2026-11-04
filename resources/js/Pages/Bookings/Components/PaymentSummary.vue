<template>
  <div class="w-full xl:w-4/12 px-4">
    <div class="p-6 md:p-12 bg-blue-300 dark:bg-blue-900">
      <h2 class="mb-6 text-4xl font-bold font-heading text-white">
        Summary
      </h2>
      <div class="flex mb-8 items-center justify-between pb-5 border-b border-blue-100">
        <span class="text-blue-50">Agreed Price</span>
        <span class="text-xl font-bold font-heading text-white">${{ typeof booking.price === 'string' ? booking.price : Number(booking.price).toFixed(2) }}</span>
      </div>
      <div
        v-if="hasDeposit"
        class="mb-8 pb-5 border-b border-blue-100"
        data-testid="deposit-summary"
      >
        <div class="flex justify-between items-center">
          <span class="text-blue-50">{{ depositName }}</span>
          <span class="text-xl font-bold font-heading text-white">${{ depositAmount }}</span>
        </div>
        <p
          v-if="depositStatus"
          class="mt-1 text-sm text-right"
          :class="statusClass(depositState)"
        >
          {{ depositStatus }}
        </p>
      </div>
      <h4 class="mb-2 text-xl font-bold font-heading text-white">
        Payment Total
      </h4>
      <div class="flex mb-2 justify-between items-center">
        <span class="text-blue-50">Amount Paid</span>
        <span class="text-xl font-bold font-heading text-white">${{ typeof booking.amountPaid === 'string' ? booking.amountPaid : Number(booking.amountPaid).toFixed(2) }}</span>
      </div>
      <div
        class="flex justify-between items-center"
        :class="balanceStatus ? 'mb-1' : 'mb-10'"
      >
        <span class="text-xl font-bold font-heading text-white">Payment Left</span>
        <span class="text-xl font-bold font-heading text-white">${{ typeof booking.amountLeft === 'string' ? booking.amountLeft : Number(booking.amountLeft).toFixed(2) }}</span>
      </div>
      <p
        v-if="balanceStatus"
        class="mb-10 text-sm text-right"
        :class="statusClass(balanceState)"
        data-testid="balance-status"
      >
        {{ balanceStatus }}
      </p>
      <div
        v-if="booking.amountLeft === '0.00'"
        class="text-2xl font-bold font-heading text-white text-center"
      >
        <span class="underline">PAID</span> <i class="pi pi-check" />
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, toRef } from 'vue';
import { useDeposit } from '@/composables/useDeposit';
import { depositLabel, formatDueDate, formatMoney } from '@/composables/usePaymentDueText';

const props = defineProps({
  booking: {
    type: Object,
    required: true
  }
});

const { depositAmount } = useDeposit(toRef(props, 'booking'));

const due = computed(() => props.booking.payment_due ?? null);
const depositState = computed(() => due.value?.deposit ?? null);
const balanceState = computed(() => due.value?.balance ?? null);

// A booking without a contract has no deposit to track.
const hasDeposit = computed(() => parseFloat(depositAmount.value) > 0
  && (due.value !== null || props.booking.contract_option !== 'none'));

const depositName = computed(() => depositLabel(props.booking.deposit_type, props.booking.deposit_value));

const depositStatus = computed(() => {
  const state = depositState.value;
  if (!state) {
    // A cancelled booking or a signed but unconfirmed one will never get this
    // date, so only promise it while the contract is still awaiting signature.
    const awaitingSignature = props.booking.status !== 'cancelled'
      && props.booking.contract?.status !== 'completed';
    return awaitingSignature ? 'Due 3 weeks after signing' : null;
  }
  if (parseFloat(state.owed) <= 0) return 'Deposit paid';
  if (state.status === 'overdue') {
    return `${formatMoney(state.owed)} overdue since ${formatDueDate(state.overdue_since)}`;
  }
  return `${formatMoney(state.owed)} still due by ${formatDueDate(state.due_date)}`;
});

const balanceStatus = computed(() => {
  const state = balanceState.value;
  if (!state || parseFloat(state.owed) <= 0) return null;
  if (state.status === 'overdue') return `Overdue since the ${formatDueDate(state.overdue_since)} show`;
  if (state.status === 'due_now') return `Due now, show is ${formatDueDate(due.value.event_date)}`;
  const depositOwed = parseFloat(depositState.value.owed) > 0 && !due.value.merged;
  return `${depositOwed ? 'Rest due' : 'Due'} by ${formatDueDate(state.due_date)}`;
});

const statusClass = (state) => {
  if (state && parseFloat(state.owed) > 0 && state.status === 'overdue') {
    return 'inline-block w-full rounded bg-red-800 px-2 py-1 font-semibold text-white';
  }
  return 'text-white';
};
</script>
