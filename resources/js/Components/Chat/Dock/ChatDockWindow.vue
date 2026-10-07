<template>
  <section
    class="w-80 pointer-events-auto flex flex-col rounded-t-lg shadow-2xl border border-b-0 border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 overflow-hidden"
    :class="minimized ? '' : 'h-[28rem]'"
    :aria-label="conversation.title"
  >
    <header
      data-test="window-header"
      role="button"
      tabindex="0"
      :aria-expanded="minimized ? 'false' : 'true'"
      class="flex items-center gap-2 px-3 py-2 shrink-0 cursor-pointer select-none bg-gray-50 dark:bg-slate-900 border-b border-gray-200 dark:border-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
      @click="$emit('toggle')"
      @keydown.enter.prevent="$emit('toggle')"
      @keydown.space.prevent="$emit('toggle')"
    >
      <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 shrink-0">
        <i :class="['pi', icon, 'text-sm']" />
      </span>
      <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-semibold text-gray-900 dark:text-gray-50">{{ conversation.title }}</span>
        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ subtitle }}</span>
      </span>
      <span
        v-if="minimized && conversation.unread_count > 0"
        data-test="window-unread-pill"
        class="shrink-0 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-500 text-white text-xs font-semibold"
      >{{ conversation.unread_count }}</span>
      <button
        type="button"
        data-test="window-minimize"
        class="p-1 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-700"
        :aria-label="minimized ? 'Restore' : 'Minimize'"
        @click.stop="$emit('toggle')"
      >
        <i :class="['pi text-sm', minimized ? 'pi-window-maximize' : 'pi-minus']" />
      </button>
      <button
        type="button"
        data-test="window-close"
        class="p-1 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-700"
        aria-label="Close"
        @click.stop="$emit('close')"
      >
        <i class="pi pi-times text-sm" />
      </button>
    </header>

    <!-- Minimized = not reading: unmount the thread so no read acks fire. -->
    <ConversationThread
      v-if="!minimized"
      :load-url="route('chat.conversations.messages.index', conversation.id)"
      :current-user-id="currentUserId"
      noun="message"
      class="flex-1 min-h-0 bg-gray-50 dark:bg-slate-900"
      @read="$emit('read')"
    />
  </section>
</template>

<script setup>
import { computed } from 'vue';
import ConversationThread from '@/Components/Chat/ConversationThread.vue';
import { conversationSubtitle } from '@/utils/conversationLabels';

const props = defineProps({
  conversation: { type: Object, required: true },
  minimized: { type: Boolean, default: false },
  currentUserId: { type: Number, required: true },
});
defineEmits(['close', 'toggle', 'read']);

const ICONS = { dm: 'pi-user', band: 'pi-users', booking: 'pi-briefcase', event: 'pi-calendar', rehearsal: 'pi-headphones' };

const icon = computed(() => {
  const c = props.conversation;
  if (c.type === 'topic') return ICONS[c.topic_type] ?? 'pi-comment';
  return ICONS[c.type] ?? 'pi-comment';
});

const subtitle = computed(() => conversationSubtitle(props.conversation));
</script>
