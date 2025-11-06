<template>
  <FinanceLayout>
    <div class="mx-4 my-6 space-y-8">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
            Payment Calculator
          </h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            Configure how payments are distributed to band members
          </p>
        </div>
      </div>

      <div
        v-for="band in bands"
        :key="band.id"
        class="space-y-6"
      >
        <PaymentGroupManager :band="band" />

        <div class="componentPanel shadow-lg rounded-lg p-6 space-y-6">
          <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 border-b pb-3">
            {{ band.name }} - Payout Calculator
          </h2>

          <QuickCalculator
            v-model:total-amount="calculators[band.id].totalAmount"
            @calculate="handleCalculate(band)"
          />

          <PayoutResults :results="results[band.id]" />

          <ConfigSection
            v-model:editing-config="configs[band.id]"
            :config="band.active_payout_config"
            :band="band"
            :owner-count="band.owners.length"
            :member-count="band.members.length"
            :is-editing="editingConfig[band.id]"
            :saving="saving[band.id]"
            @edit="startEditing(band)"
            @cancel="cancelEditing(band.id)"
            @save="saveConfiguration(band)"
          />
        </div>
      </div>
    </div>
  </FinanceLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import FinanceLayout from './Layout/FinanceLayout.vue'
import PaymentGroupManager from './Components/PaymentGroupManager.vue'
import QuickCalculator from './Components/QuickCalculator.vue'
import PayoutResults from './Components/PayoutResults.vue'
import ConfigSection from './Components/ConfigSection.vue'
import { usePayoutCalculator } from '@/composables/usePayoutCalculator'

const props = defineProps({
  bands: {
    type: Array,
    required: true
  }
})

const { calculate, getDefaultConfig } = usePayoutCalculator()

const calculators = reactive({})
const results = reactive({})
const editingConfig = reactive({})
const saving = reactive({})
const configs = reactive({})

props.bands.forEach(band => {
  calculators[band.id] = { totalAmount: 5000 }
  results[band.id] = band.active_payout_config ? calculate(band, 5000) : null
  editingConfig[band.id] = false
  saving[band.id] = false
  configs[band.id] = getDefaultConfig(band)
})

function handleCalculate(band) {
  results[band.id] = calculate(band, calculators[band.id].totalAmount)
}

function startEditing(band) {
  editingConfig[band.id] = true
  configs[band.id] = getDefaultConfig(band)
}

function cancelEditing(bandId) {
  editingConfig[bandId] = false
  const band = props.bands.find(b => b.id === bandId)
  configs[bandId] = getDefaultConfig(band)
}

function saveConfiguration(band) {
  saving[band.id] = true
  
  const url = band.active_payout_config 
    ? `/finances/payout-config/${band.id}/${band.active_payout_config.id}`
    : `/finances/payout-config/${band.id}`
  
  const method = band.active_payout_config ? 'put' : 'post'
  
  router[method](url, {
    ...configs[band.id],
    is_active: true
  }, {
    onSuccess: () => {
      saving[band.id] = false
      editingConfig[band.id] = false
    },
    onError: (errors) => {
      saving[band.id] = false
      console.error('Save failed:', errors)
    }
  })
}
</script>
