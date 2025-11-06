<template>
  <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 rounded-lg p-6">
    <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-100">
      <i class="pi pi-calculator mr-2" />
      Quick Calculator
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Total Booking Amount
        </label>
        <InputNumber
          :model-value="totalAmount"
          mode="currency"
          currency="USD"
          locale="en-US"
          class="w-full"
          :min="0"
          @update:model-value="$emit('update:totalAmount', $event)"
          @input="$emit('calculate')"
        />
      </div>
      <div class="flex items-end">
        <Button
          label="Calculate"
          icon="pi pi-chart-bar"
          class="w-full"
          @click="$emit('calculate')"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'

defineProps({
  totalAmount: {
    type: Number,
    default: 5000
  }
})

defineEmits(['update:totalAmount', 'calculate'])
</script>
