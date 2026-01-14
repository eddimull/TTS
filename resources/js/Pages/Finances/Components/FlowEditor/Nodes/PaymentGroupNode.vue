<template>
  <BaseFlowNode
    :id="id"
    :data="data"
    :border-color="borderColorClass"
    width="min-w-[280px]"
    :title="data.groupName || 'Select Group'"
    :subtitle="`${data.memberCount || 0} members`"
    header-justify="justify-between"
    :handles="handles"
    :calculated-values="calculatedValues"
    @update="(...args) => emit('update', ...args)"
    @settings="emit('settings', $event)"
    @delete="(...args) => emit('delete', ...args)"
    @duplicate="(...args) => emit('duplicate', ...args)"
    @rename="(...args) => emit('rename', ...args)"
  >
    <template #icon>
      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 text-xs font-bold">
        {{ (data.displayOrder ?? 0) + 1 }}
      </span>
    </template>

    <template #header-actions>
      <Button
        icon="pi pi-cog"
        text
        rounded
        size="small"
        @click="emit('settings', id)"
        v-tooltip.left="'Configure group'"
      />
    </template>

    <template #content>
      <!-- Group Selection (if no group assigned) -->
      <div v-if="!data.groupId" class="mb-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded">
        <div class="flex items-start gap-2">
          <i class="pi pi-exclamation-triangle text-yellow-600 dark:text-yellow-400 mt-0.5" />
          <div class="text-sm text-yellow-800 dark:text-yellow-200">
            <p class="font-semibold mb-1">Group not assigned</p>
            <Select
              v-model="localGroupId"
              :options="availableGroups"
              option-label="name"
              option-value="id"
              placeholder="Select payment group"
              class="w-full"
              @update:model-value="handleGroupChange"
            />
          </div>
        </div>
      </div>

      <!-- Allocation Configuration -->
      <div class="space-y-2 mb-3">
        <div>
          <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Allocation Type</label>
          <div class="flex gap-2">
            <Select
              v-model="localAllocationType"
              :options="allocationTypeOptions"
              option-label="label"
              option-value="value"
              class="flex-1"
              @update:model-value="handleUpdate"
            />
          </div>
        </div>

        <div>
          <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">
            {{ localAllocationType === 'percentage' ? 'Percentage of Remaining' : 'Fixed Amount' }}
          </label>
          <InputNumber
            v-model="localAllocationValue"
            :mode="localAllocationType === 'percentage' ? 'decimal' : 'currency'"
            :suffix="localAllocationType === 'percentage' ? '%' : ''"
            :currency="localAllocationType === 'fixed' ? 'USD' : undefined"
            :min="0"
            :max="localAllocationType === 'percentage' ? 100 : undefined"
            class="w-full"
            @update:model-value="handleUpdate"
          />
        </div>
      </div>
    </template>
  </BaseFlowNode>
</template>

<script setup>
import { computed } from 'vue'
import { Position } from '@vue-flow/core'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
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
  },
  availableGroups: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update', 'settings', 'delete', 'duplicate', 'rename'])

const { useSyncedRef, emitUpdate } = useFlowNode(props, emit)

const allocationTypeOptions = [
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' }
]

const localGroupId = useSyncedRef('groupId', null)
const localAllocationType = useSyncedRef('allocationType', 'percentage')
const localAllocationValue = useSyncedRef('allocationValue', 0, true)

const handles = [
  { type: 'target', position: Position.Left, id: 'group-in' },
  { type: 'source', position: Position.Right, id: 'group-out' }
]

// Border color based on display order
const borderColorClass = computed(() => {
  const colors = ['border-blue-500', 'border-green-500', 'border-orange-500', 'border-purple-500', 'border-pink-500', 'border-indigo-500']
  return colors[(props.data.displayOrder ?? 0) % colors.length]
})

const calculatedValues = computed(() =>
  props.data.input !== undefined ? [
    {
      label: 'Input',
      value: props.data.input || 0,
      format: 'money',
      class: 'font-medium'
    },
    {
      label: 'Allocation',
      value: props.data.allocation || 0,
      format: 'money',
      class: 'font-bold text-green-600'
    },
    {
      label: 'To Next',
      value: props.data.output || 0,
      format: 'money',
      class: 'font-medium text-blue-600'
    }
  ] : []
)

const handleGroupChange = () => {
  const selectedGroup = props.availableGroups.find(g => g.id === localGroupId.value)
  if (selectedGroup) {
    emitUpdate({
      groupId: selectedGroup.id,
      groupName: selectedGroup.name,
      memberCount: selectedGroup.users?.length || 0
    })
  }
}

const handleUpdate = () => {
  emitUpdate({
    allocationType: localAllocationType.value,
    allocationValue: localAllocationValue.value
  })
}
</script>

<style scoped>
:deep(.vue-flow__handle[id="group-in"]) {
  background: #3b82f6;
}

:deep(.vue-flow__handle[id="group-out"]) {
  background: #3b82f6;
}

:deep(.vue-flow__handle[id="group-members"]) {
  background: #10b981;
}
</style>
