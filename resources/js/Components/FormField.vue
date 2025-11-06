<template>
  <div>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
      {{ label }}
    </label>
    <slot />
    <small
      v-if="hint"
      class="text-gray-500 dark:text-gray-400 mt-1 block"
    >
      {{ hint }}
    </small>
  </div>
</template>

<script setup>
defineProps({
  label: {
    type: String,
    required: true
  },
  hint: {
    type: String,
    default: ''
  }
})
</script>
