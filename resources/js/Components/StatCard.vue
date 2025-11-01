<template>
  <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
    <p class="text-sm text-gray-600 dark:text-gray-400">
      {{ label }}
    </p>
    <p
      class="text-2xl font-bold"
      :class="valueColorClass"
    >
      {{ value }}
    </p>
  </div>
</template>

<script setup>
defineProps({
  label: {
    type: String,
    required: true
  },
  value: {
    type: String,
    required: true
  },
  valueColorClass: {
    type: String,
    default: 'text-gray-800 dark:text-gray-100'
  }
})
</script>
