<template>
  <BaseFlowNode
    :id="id"
    :data="data"
    border-color="border-cyan-500"
    icon-color="text-cyan-500"
    icon="pi pi-user"
    title="Members"
    :subtitle="data.groupName || 'Group'"
    width="min-w-[240px]"
    :handles="handles"
    :calculated-values="calculatedValues"
    :show-calculations="data.input !== undefined"
  >
    <template #content>
      <!-- Member Distribution Preview -->
      <div v-if="data.members && data.members.length > 0" class="space-y-2 mb-3">
        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300">Distribution:</div>
        <div class="max-h-48 overflow-y-auto space-y-1">
          <div
            v-for="member in data.members.slice(0, 5)"
            :key="member.user_id || member.name"
            class="flex justify-between items-center text-sm p-2 bg-gray-50 dark:bg-gray-700 rounded"
          >
            <div class="flex-1 truncate">
              <div class="font-medium text-gray-800 dark:text-gray-200">
                {{ member.user_name || member.name }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ formatPayoutType(member.payout_type) }}
              </div>
            </div>
            <div class="font-bold text-green-600 ml-2">
              {{ moneyFormat(member.amount || 0) }}
            </div>
          </div>
        </div>
        <div v-if="data.members.length > 5" class="text-xs text-center text-gray-500 dark:text-gray-400">
          +{{ data.members.length - 5 }} more
        </div>
      </div>

      <!-- No Members Message -->
      <div v-else class="p-3 bg-gray-50 dark:bg-gray-700 rounded text-sm text-center text-gray-500 dark:text-gray-400">
        <i class="pi pi-users mb-2 text-2xl" />
        <p>No members configured</p>
      </div>
    </template>
  </BaseFlowNode>
</template>

<script setup>
import { computed } from 'vue'
import { Position } from '@vue-flow/core'
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

const { moneyFormat } = useFlowNode(props)

const handles = [
  { type: 'target', position: Position.Left, id: 'member-in' }
]

const calculatedValues = computed(() => [
  {
    label: 'Total',
    value: props.data.input || 0,
    format: 'money',
    class: 'text-lg font-bold text-green-600'
  }
])

const formatPayoutType = (type) => {
  const types = {
    percentage: 'Percentage',
    fixed: 'Fixed',
    equal_split: 'Equal Split'
  }
  return types[type] || type
}
</script>

<style scoped>
:deep(.vue-flow__handle) {
  background: #06b6d4;
}

:deep(.vue-flow__handle:hover) {
  background: #0891b2;
  transform: scale(1.2);
}
</style>
