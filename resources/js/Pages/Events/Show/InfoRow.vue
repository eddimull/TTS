<template>
  <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2">
    <span class="text-gray-500 dark:text-gray-400 font-medium min-w-24">
      {{ label }}:
    </span>
    <component
      :is="isLink ? 'a' : 'span'"
      :href="href"
      :class="isLink ? 'text-blue-600 dark:text-blue-400 hover:underline' : 'text-gray-900 dark:text-gray-100'"
    >
      <slot>{{ value }}</slot>
    </component>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: {
    type: String,
    required: true
  },
  value: {
    type: String,
    default: null
  },
  href: {
    type: String,
    default: null
  }
});

const isLink = computed(() => !!props.href);
</script>
