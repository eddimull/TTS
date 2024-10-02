<template>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <ContractNone
      v-if="booking.contract_option === 'none'"
      class="mb-8"
    />
    <ContractExternal
      v-if="booking.contract_option === 'external' && booking.status !== 'confirmed'"
      :booking="booking"
      class="mb-8"
    />
    
    <ContractEditor
      v-if="booking.contract_option === 'default' && booking.status !== 'confirmed' && booking.status !== 'pending'" 
      :booking="booking"
      :band="band"
      class="mb-8"
    />

    <div v-if="booking.status === 'confirmed' || booking.status === 'pending' && booking.contract_option === 'default'" class="space-y-6">
      <p v-if="booking.status === 'confirmed'" class="text-xl text-center text-gray-700 font-semibold bg-yellow-100 py-3 px-4 rounded-lg shadow-sm">
        This contract is confirmed. The contract is no longer editable.
      </p>
      <p v-if="booking.status === 'pending'" class="text-xl text-center text-gray-700 font-semibold bg-blue-100 py-3 px-4 rounded-lg shadow-sm">
        This contract is pending. The contract is no longer editable.
      </p>
      <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 bg-white rounded-lg shadow-md overflow-hidden">
          <h2 class="text-lg font-semibold bg-gray-100 px-4 py-2 border-b">Contract Preview</h2>
          <iframe
            v-if="booking.contract?.asset_url"
            :src="booking.contract.asset_url"
            width="100%"
            height="800px"
            class="border-0"
          />
        </div>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
          <h2 class="text-lg font-semibold bg-gray-100 px-4 py-2 border-b">Contract Status</h2>
          <div class="p-4 overflow-y-auto" style="height: 800px;">
            <ContractStatus :contract="booking.contract" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import BookingLayout from './Layout/BookingLayout.vue'  
import ContractEditor from './Components/ContractEditor.vue'
import ContractNone from './Components/ContractNone.vue';
import ContractExternal from './Components/ContractExternal.vue';
import ContractStatus from './Components/ContractStatus.vue';

defineOptions({
  layout: BookingLayout,
})

const props = defineProps({
  booking: Object,
  band: Object,
})
</script>