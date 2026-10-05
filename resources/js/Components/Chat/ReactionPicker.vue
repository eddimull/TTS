<template>
  <div
    class="inline-flex items-center gap-1 rounded-full bg-white dark:bg-slate-700 shadow-md border border-gray-200 dark:border-slate-600 px-2 py-1"
    role="group"
    aria-label="React"
  >
    <button
      v-for="emoji in QUICK_REACTIONS"
      :key="emoji"
      type="button"
      class="text-lg leading-none px-1 rounded hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors"
      :aria-label="`React ${emoji}`"
      @click.stop="$emit('pick', emoji)"
    >
      {{ emoji }}
    </button>
  </div>
</template>

<script setup>
import { QUICK_REACTIONS } from './reactions';

defineEmits(['pick']);
</script>
