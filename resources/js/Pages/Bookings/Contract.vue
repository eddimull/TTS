<template>
  <!-- <ContractForm
    :booking="booking"
    :band="band"
  /> -->
  <ContractEditor
    :booking="booking"
    :band="band"
  />
</template>
  
  <script setup>
  import BookingLayout from './Layout/BookingLayout.vue'
  import ContractForm from './Components/ContractForm.vue'
  import ContractEditor from './Components/ContractEditor.vue'

  
  defineOptions({
    layout: BookingLayout,
  })
  
  const props = defineProps({
    booking: Object,
    band: Object,
  })
  </script>