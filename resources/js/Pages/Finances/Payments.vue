<template>
  <FinanceLayout>
    <Payments :payments="payments" />
  </FinanceLayout>
</template>
  
<script setup>
 import FinanceLayout from './Layout/FinanceLayout.vue'
 import Payments from './Components/AllPayments.vue'
 const props = defineProps({
   payments: {
     type: Object,
     required: true
   }
 })
</script>