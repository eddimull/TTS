<template>
  <BaseFlowNode
    :id="id"
    :data="data"
    border-color="border-amber-500"
    icon-color="text-amber-500"
    icon="pi pi-question-circle"
    :title="nodeTitle"
    width="min-w-[260px]"
    header-justify="justify-between"
    :handles="handles"
    :calculated-values="calculatedValues"
    @update="(...args) => emit('update', ...args)"
    @settings="() => showDialog = true"
    @delete="(...args) => emit('delete', ...args)"
    @duplicate="(...args) => emit('duplicate', ...args)"
    @rename="(...args) => emit('rename', ...args)"
  >
    <template #header-actions>
      <Button
        icon="pi pi-cog"
        text
        rounded
        size="small"
        @click="showDialog = true"
        v-tooltip.left="'Configure condition'"
      />
    </template>

    <template #content>
      <div class="space-y-2">
        <!-- Condition Summary -->
        <div class="bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg border border-amber-200 dark:border-amber-700">
          <div class="flex items-center gap-2 mb-2">
            <i class="pi pi-code text-amber-600 dark:text-amber-400" />
            <span class="text-xs font-semibold text-amber-800 dark:text-amber-300">Condition</span>
          </div>
          <div class="text-sm text-gray-700 dark:text-gray-300 font-mono bg-white dark:bg-gray-800 p-2 rounded">
            {{ conditionSummary }}
          </div>
        </div>

        <!-- Branch Labels -->
        <div class="grid grid-cols-2 gap-2 text-xs">
          <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded border border-green-200 dark:border-green-700">
            <i class="pi pi-check-circle text-green-600 dark:text-green-400 mr-1" />
            <span class="font-medium text-green-700 dark:text-green-300">TRUE</span>
          </div>
          <div class="bg-red-50 dark:bg-red-900/20 p-2 rounded border border-red-200 dark:border-red-700">
            <i class="pi pi-times-circle text-red-600 dark:text-red-400 mr-1" />
            <span class="font-medium text-red-700 dark:text-red-300">FALSE</span>
          </div>
        </div>
      </div>
    </template>
  </BaseFlowNode>

  <!-- Configuration Dialog -->
  <Dialog
    v-model:visible="showDialog"
    :header="`Configure Condition: ${data.customLabel || 'Unnamed'}`"
    :modal="true"
    :closable="true"
    :style="{ width: '500px' }"
  >
    <div class="space-y-4">
      <!-- Condition Type -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Condition Type
        </label>
        <Select
          v-model="localConditionType"
          :options="CONDITION_TYPES"
          option-label="label"
          option-value="value"
          class="w-full"
          @update:model-value="handleConditionTypeChange"
        >
          <template #option="slotProps">
            <div>
              <div class="font-medium">{{ slotProps.option.label }}</div>
              <div class="text-xs text-gray-500">{{ slotProps.option.description }}</div>
            </div>
          </template>
        </Select>
      </div>

      <!-- Operator -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Operator
        </label>
        <Select
          v-model="localOperator"
          :options="availableOperators"
          class="w-full"
        />
      </div>

      <!-- Value -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Value
        </label>

        <!-- Currency input -->
        <InputNumber
          v-if="selectedConditionInputType === 'currency'"
          v-model="localValue"
          mode="currency"
          currency="USD"
          class="w-full"
        />

        <!-- Number input -->
        <InputNumber
          v-else-if="selectedConditionInputType === 'number'"
          v-model="localValue"
          :min="0"
          class="w-full"
        />

        <!-- Select input -->
        <Select
          v-else-if="selectedConditionInputType === 'select'"
          v-model="localValue"
          :options="selectedConditionOptions"
          class="w-full"
        />

        <!-- Text input fallback -->
        <InputText
          v-else
          v-model="localValue"
          class="w-full"
        />
      </div>

      <!-- Preview -->
      <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg border border-blue-200 dark:border-blue-700">
        <div class="text-xs font-semibold text-blue-800 dark:text-blue-300 mb-1">Preview</div>
        <div class="text-sm text-gray-700 dark:text-gray-300 font-mono">
          {{ previewCondition }}
        </div>
      </div>
    </div>

    <template #footer>
      <Button label="Cancel" text @click="showDialog = false" />
      <Button label="Save" @click="handleSave" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Position } from '@vue-flow/core'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import BaseFlowNode from '../BaseFlowNode.vue'
import { useFlowNode } from '../useFlowNode'
import { CONDITION_TYPES } from '@/composables/useFlowNodeSchemas'

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

const showDialog = ref(false)
const localConditionType = useSyncedRef('conditionType', 'bookingPrice')
const localOperator = useSyncedRef('operator', '>')
const localValue = useSyncedRef('value', 1000, true)

// Node title
const nodeTitle = computed(() => {
  return props.data.customLabel || 'Conditional'
})

// Get the selected condition type configuration
const selectedCondition = computed(() => {
  return CONDITION_TYPES.find(c => c.value === localConditionType.value) || CONDITION_TYPES[0]
})

// Available operators for the selected condition type
const availableOperators = computed(() => {
  return selectedCondition.value.operators || ['>', '<', '>=', '<=', '==', '!=']
})

// Input type for the value field
const selectedConditionInputType = computed(() => {
  return selectedCondition.value.inputType || 'text'
})

// Options for select-type conditions
const selectedConditionOptions = computed(() => {
  return selectedCondition.value.options || []
})

// Condition summary for display
const conditionSummary = computed(() => {
  const condition = selectedCondition.value
  const operator = localOperator.value
  let value = localValue.value

  // Format value based on input type
  if (condition.inputType === 'currency') {
    value = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value)
  }

  return `${condition.label} ${operator} ${value}`
})

// Preview condition for dialog
const previewCondition = computed(() => {
  return conditionSummary.value
})

// Handles - one input, two outputs (true/false)
const handles = [
  { type: 'target', position: Position.Left, id: 'conditional-in' },
  { type: 'source', position: Position.Right, id: 'true', style: { top: '33%' } },
  { type: 'source', position: Position.Right, id: 'false', style: { top: '66%' } }
]

// Calculated values for visualization
const calculatedValues = computed(() => {
  if (!props.data.input) return []

  const result = []

  result.push({
    label: 'Input',
    value: props.data.input,
    format: 'money',
    class: 'font-medium'
  })

  if (props.data.trueOutput !== undefined) {
    result.push({
      label: 'TRUE Branch',
      value: props.data.trueOutput,
      format: 'money',
      class: 'font-bold text-green-600'
    })
  }

  if (props.data.falseOutput !== undefined) {
    result.push({
      label: 'FALSE Branch',
      value: props.data.falseOutput,
      format: 'money',
      class: 'font-bold text-red-600'
    })
  }

  return result
})

// Handle condition type change
const handleConditionTypeChange = () => {
  // Reset operator to first available for new condition type
  const newCondition = CONDITION_TYPES.find(c => c.value === localConditionType.value)
  if (newCondition && newCondition.operators.length > 0) {
    localOperator.value = newCondition.operators[0]
  }

  // Reset value based on input type
  if (newCondition) {
    switch (newCondition.inputType) {
      case 'currency':
      case 'number':
        localValue.value = newCondition.inputType === 'currency' ? 1000 : 1
        break
      case 'select':
        localValue.value = newCondition.options[0]
        break
      default:
        localValue.value = ''
    }
  }
}

// Save configuration
const handleSave = () => {
  emitUpdate({
    conditionType: localConditionType.value,
    operator: localOperator.value,
    value: localValue.value
  })
  showDialog.value = false
}
</script>

<style scoped>
/* TRUE handle - green */
:deep(.vue-flow__handle#true) {
  background: #10b981;
  border: 2px solid #059669;
}

:deep(.vue-flow__handle#true:hover) {
  background: #059669;
}

/* FALSE handle - red */
:deep(.vue-flow__handle#false) {
  background: #ef4444;
  border: 2px solid #dc2626;
}

:deep(.vue-flow__handle#false:hover) {
  background: #dc2626;
}

/* Input handle - amber */
:deep(.vue-flow__handle#conditional-in) {
  background: #f59e0b;
  border: 2px solid #d97706;
}

:deep(.vue-flow__handle#conditional-in:hover) {
  background: #d97706;
}
</style>
