<template>
  <BaseFlowNode
    :id="id"
    :data="data"
    border-color="border-blue-500"
    icon-color="text-blue-500"
    icon="pi pi-dollar"
    :title="data.label"
    :handles="handles"
    :calculated-values="calculatedValues"
    @update="(...args) => emit('update', ...args)"
    @delete="(...args) => emit('delete', ...args)"
    @duplicate="(...args) => emit('duplicate', ...args)"
    @rename="(...args) => emit('rename', ...args)"
  >
    <template #content>
      <div class="mt-2">
        <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Total Income</label>
        <InputNumber
          v-model="localAmount"
          mode="currency"
          currency="USD"
          locale="en-US"
          class="w-full"
          @update:model-value="handleUpdate"
        />
      </div>
    </template>
  </BaseFlowNode>
</template>

<script setup>
import { computed } from 'vue'
import { Position } from '@vue-flow/core'
import InputNumber from 'primevue/inputnumber'
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

const emit = defineEmits(['update', 'delete', 'duplicate', 'rename'])

const { useSyncedRef, emitUpdate } = useFlowNode(props, emit)

const localAmount = useSyncedRef('amount', 0, true)

const handles = [
  { type: 'source', position: Position.Right, id: 'income-out' }
]

const calculatedValues = computed(() => [
  {
    label: 'Output',
    value: localAmount.value || 0,
    format: 'money',
    class: 'text-lg font-bold text-green-600'
  }
])

const handleUpdate = () => {
  emitUpdate({ amount: localAmount.value })
}
</script>

<style scoped>
:deep(.vue-flow__handle) {
  background: #3b82f6;
}

:deep(.vue-flow__handle:hover) {
  background: #2563eb;
}
</style>
