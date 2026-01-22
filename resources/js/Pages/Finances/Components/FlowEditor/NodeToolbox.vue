<template>
  <div class="absolute left-4 top-4 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4 w-56 z-10">
    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
      <i class="pi pi-th-large" />
      Node Toolbox
    </h3>

    <div class="space-y-2">
      <div
        v-for="nodeType in availableNodes"
        :key="nodeType.type"
        :draggable="!nodeType.disabled"
        @dragstart="handleDragStart($event, nodeType.type)"
        :class="[
          'w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors',
          nodeType.disabled
            ? 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed'
            : 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-grab active:cursor-grabbing'
        ]"
      >
        <i :class="`pi ${nodeType.icon} text-lg`" :style="{ color: nodeType.color }" />
        <div class="flex-1">
          <div>{{ nodeType.label }}</div>
          <div v-if="nodeType.description" class="text-xs text-gray-500 dark:text-gray-400">
            {{ nodeType.description }}
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
      <p class="flex items-start gap-2">
        <i class="pi pi-info-circle mt-0.5" />
        <span>Drag nodes onto the canvas to add them</span>
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ 
  nodes: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['addNode'])

const availableNodes = computed(() => {
  // Check if Income node already exists (only one allowed)
  const hasIncomeNode = props.nodes.some(n => n.type === 'income')

  return [
    {
      type: 'income',
      label: 'Income',
      description: 'Starting point',
      icon: 'pi-dollar',
      color: '#3b82f6',
      disabled: hasIncomeNode
    },
    {
      type: 'bandCut',
      label: 'Band Cut',
      description: 'Org. percentage',
      icon: 'pi-percentage',
      color: '#a855f7',
      disabled: false
    },
    {
      type: 'conditional',
      label: 'Conditional',
      description: 'Branch logic',
      icon: 'pi-question-circle',
      color: '#f59e0b',
      disabled: false
    },
    {
      type: 'payoutGroup',
      label: 'Payout Group',
      description: 'Unified distribution',
      icon: 'pi-users',
      color: '#3b82f6',
      disabled: false
    }
  ]
})

const handleDragStart = (event, nodeType) => {
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('application/vueflow', nodeType)
    event.dataTransfer.setData('nodeType', nodeType)
  }
}
</script>

<style scoped>
[draggable="false"] {
  opacity: 0.5;
}
</style>
