<template>
  <div class="flex flex-col h-full min-h-0 bg-white dark:bg-slate-800">
    <div class="p-3 border-b border-gray-200 dark:border-slate-600 flex items-center gap-2">
      <span class="relative flex-1">
        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm" />
        <input
          v-model="query"
          type="search"
          placeholder="Search messages"
          aria-label="Search conversations"
          class="w-full pl-9 pr-3 py-2 text-sm rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
      </span>
      <Button
        v-if="showNew"
        icon="pi pi-pencil"
        rounded
        aria-label="New message"
        data-test="new-message"
        @click="$emit('new')"
      />
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto p-2 space-y-0.5">
      <p
        v-if="!filtered.length"
        class="text-sm text-gray-500 dark:text-gray-400 text-center py-8"
      >
        {{ conversations.length ? 'No conversations match.' : 'No messages yet.' }}
      </p>
      <ConversationRow
        v-for="c in filtered"
        :key="c.id"
        :conversation="c"
        :selected="c.id === selectedId"
        @select="$emit('select', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import ConversationRow from './ConversationRow.vue';

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  selectedId: { type: Number, default: null },
  showNew: { type: Boolean, default: true },
});
defineEmits(['select', 'new']);

const query = ref('');

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return props.conversations;
  return props.conversations.filter((c) =>
    (c.title ?? '').toLowerCase().includes(q) || (c.last_message_preview ?? '').toLowerCase().includes(q));
});
</script>
