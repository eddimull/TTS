<template>
  <div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <FormField
        label="Band's Cut Type"
        :hint="getBandCutTypeDescription(config.band_cut_type)"
      >
        <Select
          :model-value="config.band_cut_type"
          :options="bandCutTypes"
          option-label="label"
          option-value="value"
          class="w-full"
          @update:model-value="$emit('update', { band_cut_type: $event })"
        />
      </FormField>
      <FormField
        v-if="config.band_cut_type !== 'tiered'"
        label="Band's Cut Value"
      >
        <InputNumber
          v-if="config.band_cut_type === 'percentage'"
          :model-value="config.band_cut_value"
          mode="decimal"
          suffix="%"
          locale="en-US"
          class="w-full"
          :min="0"
          :max="100"
          @update:model-value="$emit('update', { band_cut_value: $event })"
        />
        <InputNumber
          v-else-if="config.band_cut_type === 'fixed'"
          :model-value="config.band_cut_value"
          mode="currency"
          currency="USD"
          locale="en-US"
          class="w-full"
          :min="0"
          @update:model-value="$emit('update', { band_cut_value: $event })"
        />
        <InputNumber
          v-else
          :model-value="config.band_cut_value"
          mode="decimal"
          locale="en-US"
          class="w-full"
          :min="0"
          disabled
        />
      </FormField>
    </div>

    <FormField
      v-if="config.band_cut_type === 'tiered'"
      label="Band's Cut Tier Configuration"
    >
      <div class="space-y-3">
        <TierConfigRow
          v-for="(tier, index) in config.band_cut_tier_config"
          :key="'band-' + index"
          :tier="tier"
          @update:tier="updateTier(index, $event)"
          @remove="removeTier(index)"
        />
        <Button
          label="Add Tier"
          icon="pi pi-plus"
          severity="secondary"
          size="small"
          text
          @click="addTier"
        />
      </div>
    </FormField>
  </div>
</template>

<script setup>
import FormField from '@/Components/FormField.vue'
import TierConfigRow from '@/Components/TierConfigRow.vue'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Button from 'primevue/button'

const props = defineProps({
  config: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['update'])

const bandCutTypes = [
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' },
  { label: 'Tiered', value: 'tiered' },
  { label: 'None', value: 'none' }
]

const getBandCutTypeDescription = (type) => {
  const descriptions = {
    percentage: 'Band takes a percentage of the total booking amount',
    fixed: 'Band takes a fixed dollar amount',
    tiered: 'Band cut varies based on the total booking amount',
    none: 'No band cut - all money goes to members'
  }
  return descriptions[type] || ''
}

const updateTier = (index, updatedTier) => {
  const tiers = [...(props.config.band_cut_tier_config || [])]
  tiers[index] = updatedTier
  emit('update', { band_cut_tier_config: tiers })
}

const removeTier = (index) => {
  const tiers = [...(props.config.band_cut_tier_config || [])]
  tiers.splice(index, 1)
  emit('update', { band_cut_tier_config: tiers })
}

const addTier = () => {
  const tiers = [...(props.config.band_cut_tier_config || [])]
  tiers.push({ min: 0, max: 10000, type: 'percentage', value: 10 })
  emit('update', { band_cut_tier_config: tiers })
}
</script>
