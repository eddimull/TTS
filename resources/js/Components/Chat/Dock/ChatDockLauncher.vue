<template>
  <button
    type="button"
    data-test="dock-launcher"
    class="relative pointer-events-auto inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-600 hover:bg-blue-700 text-white shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-slate-900 transition-colors"
    :aria-label="count > 0 ? `Chat, ${count} unread` : 'Chat'"
    :aria-expanded="open ? 'true' : 'false'"
    @click="$emit('click')"
  >
    <i :class="['pi text-xl', open ? 'pi-times' : 'pi-comments']" />
    <span
      v-if="count > 0"
      data-test="dock-unread-pill"
      class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold leading-none"
    >{{ count }}</span>
  </button>
</template>

<script setup>
defineProps({
  count: { type: Number, default: 0 },
  open: { type: Boolean, default: false },
});
defineEmits(['click']);
</script>
