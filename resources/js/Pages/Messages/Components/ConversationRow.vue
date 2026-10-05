<template>
  <button
    type="button"
    class="w-full text-left flex items-start gap-3 px-3 py-2.5 rounded-lg transition-colors"
    :class="selected
      ? 'bg-blue-50 dark:bg-blue-900/40'
      : 'hover:bg-gray-100 dark:hover:bg-slate-700'"
    :aria-current="selected ? 'true' : undefined"
    @click="$emit('select', conversation.id)"
  >
    <span class="mt-0.5 inline-flex items-center justify-center w-9 h-9 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 shrink-0">
      <i :class="['pi', icon]" />
    </span>
    <span class="min-w-0 flex-1">
      <span class="flex items-center justify-between gap-2">
        <span
          class="truncate font-medium"
          :class="conversation.unread_count > 0 ? 'text-gray-900 dark:text-gray-50' : 'text-gray-800 dark:text-gray-200'"
        >{{ conversation.title }}</span>
        <span
          v-if="timeLabel"
          class="shrink-0 text-xs text-gray-500 dark:text-gray-400"
        >{{ timeLabel }}</span>
      </span>
      <span class="flex items-center justify-between gap-2 mt-0.5">
        <span class="truncate text-sm text-gray-500 dark:text-gray-400">{{ preview }}</span>
        <span
          v-if="conversation.unread_count > 0"
          data-test="unread-pill"
          class="shrink-0 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-500 text-white text-xs font-semibold"
        >{{ conversation.unread_count }}</span>
      </span>
    </span>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { DateTime } from 'luxon';

const props = defineProps({
  conversation: { type: Object, required: true },
  selected: { type: Boolean, default: false },
});
defineEmits(['select']);

const ICONS = { dm: 'pi-user', band: 'pi-users', booking: 'pi-briefcase', event: 'pi-calendar', rehearsal: 'pi-headphones' };

const icon = computed(() => {
  const c = props.conversation;
  if (c.type === 'topic') return ICONS[c.topic_type] ?? 'pi-comment';
  return ICONS[c.type] ?? 'pi-comment';
});

const preview = computed(() => props.conversation.last_message_preview ?? 'No messages yet');

const timeLabel = computed(() => {
  const at = props.conversation.last_message_at;
  if (!at) return '';
  return DateTime.fromISO(at).toRelative({ style: 'narrow' }) ?? '';
});
</script>
