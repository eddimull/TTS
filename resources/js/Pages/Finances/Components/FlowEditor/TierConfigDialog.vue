<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="'Configure Tiered Band Cut'"
    :style="{ width: '700px' }"
    @update:visible="handleClose"
  >
    <div class="space-y-4">
      <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-3 mb-4">
        <div class="flex items-start gap-2">
          <i class="pi pi-info-circle text-blue-600 dark:text-blue-400 mt-0.5" />
          <div class="text-sm text-blue-800 dark:text-blue-200">
            <p class="font-medium mb-1">Define tiers based on total income amount</p>
            <p class="text-xs">
              Each tier specifies a range (minimum to maximum) and the band cut for that range.
              The band cut can be a percentage of the amount in that tier or a fixed dollar amount.
            </p>
          </div>
        </div>
      </div>

      <div v-for="(tier, index) in localTiers" :key="index" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg space-y-3">
        <div class="flex items-center justify-between mb-2">
          <span class="font-semibold text-sm text-gray-700 dark:text-gray-300">Tier {{ index + 1 }}</span>
          <Button
            icon="pi pi-trash"
            text
            rounded
            severity="danger"
            size="small"
            @click="removeTier(index)"
            v-if="localTiers.length > 1"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Min Amount</label>
            <InputNumber
              v-model="tier.min"
              mode="currency"
              currency="USD"
              :min="0"
              class="w-full"
            />
          </div>
          <div>
            <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Max Amount</label>
            <InputNumber
              v-model="tier.max"
              mode="currency"
              currency="USD"
              :min="tier.min"
              placeholder="No limit"
              class="w-full"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Type</label>
            <Select
              v-model="tier.type"
              :options="[{label: 'Percentage', value: 'percentage'}, {label: 'Fixed', value: 'fixed'}]"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
          </div>
          <div>
            <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">
              {{ tier.type === 'percentage' ? 'Percentage' : 'Amount' }}
            </label>
            <InputNumber
              v-model="tier.value"
              :mode="tier.type === 'percentage' ? 'decimal' : 'currency'"
              :suffix="tier.type === 'percentage' ? '%' : ''"
              :currency="tier.type === 'fixed' ? 'USD' : undefined"
              :min="0"
              :max="tier.type === 'percentage' ? 100 : undefined"
              class="w-full"
            />
          </div>
        </div>
      </div>

      <Button
        label="Add Tier"
        icon="pi pi-plus"
        @click="addTier"
        outlined
        class="w-full"
      />

      <!-- Example -->
      <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 mt-4">
        <div class="text-xs text-gray-600 dark:text-gray-400">
          <p class="font-medium mb-2">Example:</p>
          <p class="mb-1">• $0 - $1,000 @ 10% = $100 band cut on first $1,000</p>
          <p class="mb-1">• $1,000 - $5,000 @ 15% = $600 band cut on next $4,000</p>
          <p>• $5,000+ @ 20% = 20% band cut on everything above $5,000</p>
          <p class="mt-2 italic">If income is $6,000: Band receives $100 + $600 + $200 = $900</p>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex gap-2 justify-end">
        <Button label="Cancel" severity="secondary" @click="handleClose" outlined />
        <Button label="Save" @click="handleSave" />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  tierConfig: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'save'])

const visible = ref(props.modelValue)
const localTiers = ref([])

// Initialize tiers
watch(() => props.modelValue, (newVal) => {
  visible.value = newVal
  if (newVal) {
    // Load existing tiers or create default
    if (props.tierConfig && props.tierConfig.length > 0) {
      localTiers.value = JSON.parse(JSON.stringify(props.tierConfig))
    } else {
      localTiers.value = [
        { min: 0, max: 1000, type: 'percentage', value: 10 },
        { min: 1000, max: null, type: 'percentage', value: 15 }
      ]
    }
  }
}, { immediate: true })

watch(visible, (newVal) => {
  emit('update:modelValue', newVal)
})

const addTier = () => {
  const lastTier = localTiers.value[localTiers.value.length - 1]
  const newMin = lastTier?.max || 0
  localTiers.value.push({
    min: newMin,
    max: newMin + 1000,
    type: 'percentage',
    value: 10
  })
}

const removeTier = (index) => {
  localTiers.value.splice(index, 1)
}

const handleSave = () => {
  emit('save', localTiers.value)
  visible.value = false
}

const handleClose = () => {
  visible.value = false
}
</script>
