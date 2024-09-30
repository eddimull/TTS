<template>
  <ContractNone
    v-if="booking.contract_option === 'none'"
  />
  <ContractExternal
    v-if="booking.contract_option === 'external'"
    :booking="booking"
  />
  <ContractEditor
    v-if="booking.contract_option === 'default'"
    :booking="booking"
    :band="band"
  />
</template>
  
  <script setup>
  import BookingLayout from './Layout/BookingLayout.vue'  
  import ContractEditor from './Components/ContractEditor.vue'
  import ContractNone from './Components/ContractNone.vue';
  import ContractExternal from './Components/ContractExternal.vue';

  
  defineOptions({
    layout: BookingLayout,
  })
  
  const props = defineProps({
    booking: Object,
    band: Object,
  })
  </script>