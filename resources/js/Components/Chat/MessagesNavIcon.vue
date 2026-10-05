<template>
  <Link
    :href="href"
    class="relative inline-flex items-center p-2 rounded-md text-gray-400 dark:text-gray-300 hover:text-gray-500 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
    :aria-label="count > 0 ? `Messages, ${count} unread` : 'Messages'"
  >
    <i class="pi pi-comments text-xl" />
    <span
      v-if="count > 0"
      data-test="chat-unread-pill"
      class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold leading-none"
    >{{ count }}</span>
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  count: { type: Number, default: 0 },
  href: { type: String, required: true },
});
</script>
