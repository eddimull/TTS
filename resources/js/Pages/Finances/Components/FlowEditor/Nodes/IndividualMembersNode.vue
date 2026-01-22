<template>
  <BaseFlowNode
    :id="id"
    :data="data"
    border-color="border-teal-500"
    icon-color="text-teal-500"
    icon="pi pi-users"
    title="Individual Members"
    width="min-w-[260px]"
    :handles="handles"
    :calculated-values="calculatedValues"
    @update="(...args) => emit('update', ...args)"
    @settings="emit('settings', $event)"
    @delete="(...args) => emit('delete', ...args)"
    @duplicate="(...args) => emit('duplicate', ...args)"
    @rename="(...args) => emit('rename', ...args)"
  >
    <template #content>
      <!-- Member Type Selectors -->
      <div class="space-y-3 mb-3">
        <div class="flex items-center gap-2">
          <Checkbox
            v-model="localIncludeOwners"
            input-id="include-owners"
            :binary="true"
            @update:model-value="handleUpdate"
          />
          <label for="include-owners" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
            Include Owners
          </label>
        </div>

        <div class="flex items-center gap-2">
          <Checkbox
            v-model="localIncludeMembers"
            input-id="include-members"
            :binary="true"
            @update:model-value="handleUpdate"
          />
          <label for="include-members" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
            Include Members
          </label>
        </div>
      </div>

      <!-- Payout Type Configuration -->
      <div class="space-y-2 mb-3">
        <div>
          <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Payout Type</label>
          <Select
            v-model="localMemberPayoutType"
            :options="payoutTypeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
            @update:model-value="handleUpdate"
          />
        </div>

        <!-- Production Member Count -->
        <div v-if="localMemberPayoutType !== 'member_specific'">
          <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Production Members</label>
          <InputNumber
            v-model="localProductionCount"
            :min="0"
            class="w-full"
            @update:model-value="handleUpdate"
          />
        </div>

        <!-- Minimum Payout -->
        <div>
          <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Minimum Payout</label>
          <InputNumber
            v-model="localMinimumPayout"
            mode="currency"
            currency="USD"
            :min="0"
            class="w-full"
            @update:model-value="handleUpdate"
          />
        </div>
      </div>

      <!-- Tiered Config Note -->
      <div v-if="localMemberPayoutType === 'tiered'" class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 p-2 rounded mb-3">
        <i class="pi pi-info-circle mr-1" />
        Click settings to configure tiers
      </div>

      <!-- Member Specific Config Note -->
      <div v-if="localMemberPayoutType === 'member_specific'" class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 p-2 rounded mb-3">
        <i class="pi pi-info-circle mr-1" />
        Click settings to configure per-member payouts
      </div>
    </template>
  </BaseFlowNode>
</template>

<script setup>
import { computed } from 'vue'
import { Position } from '@vue-flow/core'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import Checkbox from 'primevue/checkbox'
import BaseFlowNode from '../BaseFlowNode.vue'
import { useFlowNode } from '../useFlowNode'

const props = defineProps({
  id: {
    type: String,
    required: true
  },
  data: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['update', 'settings', 'delete', 'duplicate', 'rename'])

const { useSyncedRef, emitUpdate } = useFlowNode(props, emit)

const payoutTypeOptions = [
  { label: 'Equal Split', value: 'equal_split' },
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed', value: 'fixed' },
  { label: 'Tiered', value: 'tiered' },
  { label: 'Member Specific', value: 'member_specific' }
]

const localIncludeOwners = useSyncedRef('includeOwners', true)
const localIncludeMembers = useSyncedRef('includeMembers', true)
const localMemberPayoutType = useSyncedRef('memberPayoutType', 'equal_split')
const localProductionCount = useSyncedRef('productionCount', 0, true)
const localMinimumPayout = useSyncedRef('minimumPayout', 0, true)

const handles = [
  { type: 'target', position: Position.Left, id: 'individual-in' }
]

const calculatedValues = computed(() => {
  const values = []

  if (props.data.input !== undefined) {
    values.push({
      label: 'Distributable',
      value: props.data.input || 0,
      format: 'money',
      class: 'font-medium'
    })
  }

  if (props.data.memberCount) {
    values.push({
      label: 'Members',
      value: props.data.memberCount,
      format: 'number',
      class: 'font-medium'
    })
  }

  if (props.data.perMemberAmount) {
    values.push({
      label: 'Per Member',
      value: props.data.perMemberAmount || 0,
      format: 'money',
      class: 'font-bold text-green-600'
    })
  }

  return values
})

const handleUpdate = () => {
  emitUpdate({
    includeOwners: localIncludeOwners.value,
    includeMembers: localIncludeMembers.value,
    memberPayoutType: localMemberPayoutType.value,
    productionCount: localProductionCount.value,
    minimumPayout: localMinimumPayout.value
  })
}
</script>

<style scoped>
:deep(.vue-flow__handle) {
  background: #14b8a6;
}

:deep(.vue-flow__handle:hover) {
  background: #0d9488;
  transform: scale(1.2);
}
</style>
