<template>
  <div class="space-y-4">
    <FormField
      label="Member Payout Type"
      :hint="getMemberPayoutTypeDescription(config.member_payout_type)"
    >
      <Select
        :model-value="config.member_payout_type"
        :options="memberPayoutTypes"
        option-label="label"
        option-value="value"
        class="w-full"
        @update:model-value="$emit('update', { member_payout_type: $event })"
      />
    </FormField>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          <Checkbox
            :model-value="config.include_owners"
            :binary="true"
            class="mr-2"
            @update:model-value="$emit('update', { include_owners: $event })"
          />
          Include Owners ({{ band.owners.length }})
        </label>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          <Checkbox
            :model-value="config.include_members"
            :binary="true"
            class="mr-2"
            @update:model-value="$emit('update', { include_members: $event })"
          />
          Include Members ({{ band.members.length }})
        </label>
      </div>
      <FormField label="Production Members">
        <InputNumber
          :model-value="config.production_member_count"
          class="w-full"
          :min="0"
          @update:model-value="$emit('update', { production_member_count: $event })"
        />
      </FormField>
    </div>

    <FormField
      v-if="config.member_payout_type === 'tiered'"
      label="Tier Configuration"
    >
      <div class="space-y-3">
        <TierConfigRow
          v-for="(tier, index) in config.tier_config"
          :key="index"
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

    <MemberSpecificConfig
      v-if="config.member_payout_type === 'member_specific'"
      :config="config"
      :band="band"
      @update="$emit('update', $event)"
    />
  </div>
</template>

<script setup>
import FormField from '@/Components/FormField.vue'
import TierConfigRow from '@/Components/TierConfigRow.vue'
import MemberSpecificConfig from './MemberSpecificConfig.vue'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'

const props = defineProps({
  config: {
    type: Object,
    required: true
  },
  band: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['update'])

const memberPayoutTypes = [
  { label: 'Equal Split', value: 'equal_split' },
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' },
  { label: 'Tiered', value: 'tiered' },
  { label: 'Member Specific', value: 'member_specific' }
]

const getMemberPayoutTypeDescription = (type) => {
  const descriptions = {
    equal_split: 'All members receive an equal share of the remaining amount after band cut',
    percentage: 'Each member receives a specific percentage of the remaining amount',
    fixed: 'Each member receives a fixed dollar amount',
    tiered: 'Payout varies based on the total booking amount using tier rules',
    member_specific: 'Each member has their own individual payout configuration'
  }
  return descriptions[type] || ''
}

const updateTier = (index, updatedTier) => {
  const tiers = [...(props.config.tier_config || [])]
  tiers[index] = updatedTier
  emit('update', { tier_config: tiers })
}

const removeTier = (index) => {
  const tiers = [...(props.config.tier_config || [])]
  tiers.splice(index, 1)
  emit('update', { tier_config: tiers })
}

const addTier = () => {
  const tiers = [...(props.config.tier_config || [])]
  tiers.push({ min: 0, max: 10000, type: 'percentage', value: 10 })
  emit('update', { tier_config: tiers })
}
</script>
