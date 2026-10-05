<template>
  <button
    type="button"
    class="relative inline-flex items-center gap-2 rounded-md border border-gray-300 dark:border-slate-500 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
    @click="$emit('click')"
  >
    <i class="pi pi-comments" />
    <span>Comments</span>
    <span
      v-if="unreadCount > 0"
      data-test="unread-pill"
      class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-red-500 text-white text-xs font-semibold"
    >{{ unreadCount }}</span>
  </button>
</template>

<script setup>
defineProps({
  unreadCount: { type: Number, default: 0 },
});
defineEmits(['click']);
</script>
