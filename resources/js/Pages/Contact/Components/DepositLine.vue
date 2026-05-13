<template>
  <div v-if="depositDueDate" class="text-sm">
    <template v-if="isDepositPaid">
      <span class="text-green-700 font-medium">Deposit paid</span>
    </template>
    <template v-else>
      Deposit of <span class="font-semibold">${{ Number(amount).toFixed(2) }}</span>
      due <span class="font-semibold">{{ depositDueDate }}</span>
    </template>
  </div>
</template>

<script setup>
defineProps({
  amount: { type: [String, Number], required: true },
  isDepositPaid: { type: Boolean, required: true },
  depositDueDate: { type: String, default: null },
});
</script>
