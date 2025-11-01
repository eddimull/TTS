<template>
  <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
    <i
      v-if="icon"
      :class="`pi ${icon} mr-2`"
      :style="{ color: iconColor }"
    />
    {{ title }}
  </h4>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  icon: {
    type: String,
    default: ''
  },
  iconColor: {
    type: String,
    default: ''
  }
})
</script>
