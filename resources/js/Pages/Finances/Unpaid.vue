<template>
  <FinanceLayout>
    <AllUnpaid :unpaid="unpaid" />
  </FinanceLayout>
</template>
    
  <script setup>
   import FinanceLayout from './Layout/FinanceLayout.vue'
   import AllUnpaid from './Components/AllUnpaid.vue';
  const props = defineProps({
    unpaid: {
      type: Object,
      required: true
    }
  });
   const gotoPayments = (event) => {
     console.log(event.data)
   }
  </script>