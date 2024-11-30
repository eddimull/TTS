<template>
  <FinanceLayout>
    <AllPaid :paid="paid" />
  </FinanceLayout>
</template>
    
<script setup>
import FinanceLayout from './Layout/FinanceLayout.vue'
import AllPaid from './Components/AllPaid.vue';
const props = defineProps({
  paid: {
    type: Object,
    required: true
  }
});

</script>