<template>
  <div class="grid grid-cols-12 gap-2 items-end bg-white dark:bg-gray-700 p-3 rounded">
    <div class="col-span-3">
      <label class="text-xs text-gray-600 dark:text-gray-400">Min Amount</label>
      <InputNumber
        :model-value="tier.min"
        mode="currency"
        currency="USD"
        locale="en-US"
        class="w-full"
        :min="0"
        @update:model-value="$emit('update:tier', { ...tier, min: $event })"
      />
    </div>
    <div class="col-span-3">
      <label class="text-xs text-gray-600 dark:text-gray-400">Max Amount</label>
      <InputNumber
        :model-value="tier.max"
        mode="currency"
        currency="USD"
        locale="en-US"
        class="w-full"
        :min="tier.min"
        @update:model-value="$emit('update:tier', { ...tier, max: $event })"
      />
    </div>
    <div class="col-span-2">
      <label class="text-xs text-gray-600 dark:text-gray-400">Type</label>
      <Select
        :model-value="tier.type"
        :options="[{label: 'Percentage', value: 'percentage'}, {label: 'Fixed', value: 'fixed'}]"
        option-label="label"
        option-value="value"
        class="w-full"
        @update:model-value="$emit('update:tier', { ...tier, type: $event })"
      />
    </div>
    <div class="col-span-3">
      <label class="text-xs text-gray-600 dark:text-gray-400">Value</label>
      <InputNumber
        v-if="tier.type === 'percentage'"
        :model-value="tier.value"
        mode="decimal"
        suffix="%"
        locale="en-US"
        class="w-full"
        :min="0"
        :max="100"
        @update:model-value="$emit('update:tier', { ...tier, value: $event })"
      />
      <InputNumber
        v-else
        :model-value="tier.value"
        mode="currency"
        currency="USD"
        locale="en-US"
        class="w-full"
        :min="0"
        @update:model-value="$emit('update:tier', { ...tier, value: $event })"
      />
    </div>
    <div class="col-span-1">
      <Button
        icon="pi pi-trash"
        severity="danger"
        text
        @click="$emit('remove')"
      />
    </div>
  </div>
</template>

<script setup>
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Button from 'primevue/button'

defineProps({
  tier: {
    type: Object,
    required: true
  }
})

defineEmits(['update:tier', 'remove'])
</script>
