<template>
  <BaseFlowNode
    :id="id"
    :data="data"
    border-color="border-purple-500"
    icon-color="text-purple-500"
    icon="pi pi-percentage"
    title="Band Cut"
    width="min-w-[220px]"
    header-justify="justify-between"
    :handles="handles"
    :calculated-values="calculatedValues"
    @update="(...args) => emit('update', ...args)"
    @settings="emit('settings', $event)"
    @delete="(...args) => emit('delete', ...args)"
    @duplicate="(...args) => emit('duplicate', ...args)"
    @rename="(...args) => emit('rename', ...args)"
  >
    <template #content>
      <div class="space-y-2">
        <!-- Cut Type Selector -->
        <div>
          <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Cut Type</label>
          <Select
            v-model="localCutType"
            :options="cutTypeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
            @update:model-value="handleUpdate"
          />
        </div>

        <!-- Value Input (for percentage and fixed) -->
        <div v-if="localCutType !== 'none' && localCutType !== 'tiered'">
          <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">
            {{ localCutType === 'percentage' ? 'Percentage' : 'Amount' }}
          </label>
          <InputNumber
            v-model="localValue"
            :mode="localCutType === 'percentage' ? 'decimal' : 'currency'"
            :suffix="localCutType === 'percentage' ? '%' : ''"
            :currency="localCutType === 'fixed' ? 'USD' : undefined"
            :min="0"
            :max="localCutType === 'percentage' ? 100 : undefined"
            class="w-full"
            @update:model-value="handleUpdate"
          />
        </div>

        <!-- Tiered Config Summary -->
        <div v-if="localCutType === 'tiered'" class="space-y-2">
          <!-- Tier Summary -->
          <div v-if="data.tierConfig && data.tierConfig.length > 0" class="text-xs space-y-1">
            <div class="font-medium text-gray-700 dark:text-gray-300 mb-1">
              Configured Tiers:
            </div>
            <div
              v-for="(tier, index) in data.tierConfig"
              :key="index"
              class="bg-gray-50 dark:bg-gray-700 p-2 rounded"
            >
              <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">
                  ${{ tier.min.toLocaleString() }} - {{ tier.max ? '$' + tier.max.toLocaleString() : 'No limit' }}
                </span>
                <span class="font-medium text-purple-600 dark:text-purple-400">
                  {{ tier.type === 'percentage' ? tier.value + '%' : '$' + tier.value }}
                </span>
              </div>
            </div>
          </div>

          <!-- Configure Button -->
          <Button
            :label="data.tierConfig && data.tierConfig.length > 0 ? 'Edit Tiers' : 'Configure Tiers'"
            icon="pi pi-cog"
            size="small"
            outlined
            class="w-full"
            @click="emit('settings', id)"
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
  }
})

const emit = defineEmits(['update', 'settings', 'delete', 'duplicate', 'rename'])

const { useSyncedRef, emitUpdate } = useFlowNode(props, emit)

const cutTypeOptions = [
  { label: 'None', value: 'none' },
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' },
  { label: 'Tiered', value: 'tiered' }
]

const localCutType = useSyncedRef('cutType', 'percentage')
const localValue = useSyncedRef('value', 0, true)

const handles = [
  { type: 'target', position: Position.Left, id: 'bandcut-in' },
  { type: 'source', position: Position.Right, id: 'bandcut-out' }
]

const calculatedValues = computed(() =>
  props.data.input ? [
    {
      label: 'Input',
      value: props.data.input,
      format: 'money',
      class: 'font-medium'
    },
    {
      label: 'Band Cut',
      value: props.data.bandCut || 0,
      format: 'money',
      class: 'font-bold text-purple-600'
    },
    {
      label: 'To Members',
      value: props.data.output || 0,
      format: 'money',
      class: 'font-bold text-green-600'
    }
  ] : []
)

const handleUpdate = () => {
  emitUpdate({
    cutType: localCutType.value,
    value: localValue.value
  })
}
</script>

<style scoped>
:deep(.vue-flow__handle) {
  background: #a855f7;
}

:deep(.vue-flow__handle:hover) {
  background: #9333ea;
}
</style>
