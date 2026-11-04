<template>
  <FinanceLayout>
    <PaymentsDue :bands="bands" />
  </FinanceLayout>
</template>

<script setup>
import FinanceLayout from './Layout/FinanceLayout.vue';
import PaymentsDue from './Components/PaymentsDue.vue';

defineProps({
  bands: {
    type: Array,
    required: true,
  },
});
</script>
