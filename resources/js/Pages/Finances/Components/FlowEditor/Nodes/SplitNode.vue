<template>
  <BaseFlowNode
    :id="id"
    :data="data"
    border-color="border-orange-500"
    icon-color="text-orange-500"
    icon="pi pi-code-branch"
    title="Distribution Mode"
    :handles="handles"
    :calculated-values="calculatedValues"
    @update="(...args) => emit('update', ...args)"
    @delete="(...args) => emit('delete', ...args)"
    @duplicate="(...args) => emit('duplicate', ...args)"
    @rename="(...args) => emit('rename', ...args)"
  >
    <template #icon>
      <i class="pi pi-code-branch text-orange-500 text-xl" style="transform: rotate(180deg)" />
    </template>

    <template #content>
      <div>
        <label class="text-xs text-gray-600 dark:text-gray-400 block mb-2">Select Mode</label>
        <div class="flex gap-2">
          <button
            @click="setMode('groups')"
            :class="[
              'flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors',
              localMode === 'groups'
                ? 'bg-orange-500 text-white shadow'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
            ]"
          >
            Groups
          </button>
          <button
            @click="setMode('individual')"
            :class="[
              'flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors',
              localMode === 'individual'
                ? 'bg-orange-500 text-white shadow'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
            ]"
          >
            Individual
          </button>
        </div>
      </div>

      <!-- Output labels -->
      <div class="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
        <span :class="localMode === 'groups' ? 'font-semibold text-orange-600' : ''">
          {{ localMode === 'groups' ? '→ Groups' : '' }}
        </span>
        <span :class="localMode === 'individual' ? 'font-semibold text-orange-600' : ''">
          {{ localMode === 'individual' ? '→ Individual' : '' }}
        </span>
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

const emit = defineEmits(['update', 'delete', 'duplicate', 'rename'])

const { useSyncedRef, emitUpdate } = useFlowNode(props, emit)

const localMode = useSyncedRef('mode', 'groups')

const handles = [
  { type: 'target', position: Position.Left, id: 'split-in' },
  { type: 'source', position: Position.Right, id: 'split-groups', style: { top: '30%' } },
  { type: 'source', position: Position.Right, id: 'split-individual', style: { top: '70%' } }
]

const calculatedValues = computed(() =>
  props.data.input ? [{
    label: 'Distributable',
    value: props.data.input || 0,
    format: 'money',
    class: 'font-bold text-green-600'
  }] : []
)

const setMode = (mode) => {
  localMode.value = mode
  emitUpdate({ mode })
}
</script>

<style scoped>
:deep(.vue-flow__handle) {
  background: #f97316;
}

:deep(.vue-flow__handle:hover) {
  background: #ea580c;
}

:deep(.vue-flow__handle[id="split-in"]) {
  background: #f97316;
}

:deep(.vue-flow__handle[id="split-groups"]) {
  background: #3b82f6;
}

:deep(.vue-flow__handle[id="split-individual"]) {
  background: #10b981;
}
</style>
