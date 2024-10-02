<template>
  <ContractNone
    v-if="booking.contract_option === 'none'"
  />
  <ContractExternal
    v-if="booking.contract_option === 'external' && booking.status !== 'confirmed'"
    :booking="booking"
  />
  <ContractEditor
    v-if="booking.contract_option === 'default' && booking.status !== 'confirmed'" 
    :booking="booking"
    :band="band"
  />

  <div v-if="booking.status === 'confirmed'">
    <p class="text-lg text-center text-gray-600">This booking is confirmed. The contract is no longer editable.</p>
    <iframe
        :src="booking.contract.asset_url"
        width="100%"
        height="600px"
        class="border"
      />
  </div>
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