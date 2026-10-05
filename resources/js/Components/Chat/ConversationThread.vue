<template>
  <div class="flex flex-col h-full min-h-0">
    <!-- Scrollable messages -->
    <div
      ref="scroller"
      class="flex-1 min-h-0 overflow-y-auto px-3 py-2 space-y-2"
      @scroll="onScroll"
    >
      <div
        v-if="loading && !messages.length"
        class="text-sm text-gray-500 dark:text-gray-400 text-center py-6"
      >
        Loading comments…
      </div>

      <div
        v-else-if="error"
        class="text-sm text-center py-6 text-gray-600 dark:text-gray-300"
      >
        Comments unavailable.
        <button
          type="button"
          class="ml-1 text-blue-600 dark:text-blue-400 underline"
          @click="reload"
        >
          Retry
        </button>
      </div>

      <template v-else>
        <div
          v-if="hasMore"
          class="text-center py-1"
        >
          <button
            type="button"
            class="text-xs text-blue-600 dark:text-blue-400"
            :disabled="loadingOlder"
            @click="loadOlderKeepingOffset"
          >
            {{ loadingOlder ? 'Loading…' : 'Load earlier comments' }}
          </button>
        </div>

        <div
          v-if="!messages.length"
          class="text-sm text-gray-500 dark:text-gray-400 text-center py-6"
        >
          No comments yet. Start the conversation.
        </div>

        <template
          v-for="(m, i) in messages"
          :key="m.id"
        >
          <div
            v-if="needsDateSeparator(messages[i - 1]?.created_at, m.created_at)"
            class="text-center text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500 py-1"
          >
            {{ dateSeparatorLabel(m.created_at) }}
          </div>
          <MessageBubble
            :message="m"
            :is-own="m.user_id === currentUserId"
            :can-moderate="conversation?.can_moderate ?? false"
            :current-user-id="currentUserId"
            @react="toggleReaction(m.id, $event)"
            @edit="startEdit(m)"
            @delete="confirmDelete(m)"
            @open-attachment="openLightbox(m, $event)"
          />
        </template>
      </template>
    </div>

    <!-- Receipts + typing -->
    <div class="px-3 min-h-[1.25rem] text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
      <span v-if="typingLabel">{{ typingLabel }}</span>
      <span v-else />
      <button
        v-if="seenSummary"
        type="button"
        class="hover:underline"
        :title="seenNames"
        @click="namesOpen = !namesOpen"
      >
        {{ seenSummary }}
      </button>
    </div>
    <div
      v-if="namesOpen && seenNames"
      class="px-3 pb-1 text-xs text-gray-500 dark:text-gray-400"
    >
      {{ seenNames }}
    </div>

    <!-- Edit banner -->
    <div
      v-if="editing"
      class="px-3 py-1 text-xs bg-amber-50 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 flex items-center justify-between"
    >
      <span>Editing comment</span>
      <button
        type="button"
        class="underline"
        @click="cancelEdit"
      >
        Cancel
      </button>
    </div>
    <div
      v-if="editing"
      class="px-3 pb-2"
    >
      <textarea
        v-model="editBody"
        rows="2"
        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-50 px-2 py-1 text-sm"
        @keydown.enter.exact="onEditKeydown"
      />
      <div class="flex justify-end gap-2 mt-1">
        <Button
          label="Save"
          size="small"
          :disabled="!editBody.trim()"
          @click="saveEdit"
        />
      </div>
    </div>

    <MessageComposer
      v-else
      :disabled="!conversation || sending"
      @send="onSend"
      @typing="notifyTyping"
    />

    <ImageLightbox
      :show="lightbox.show"
      :images="lightbox.images"
      :initial-index="lightbox.index"
      @close="lightbox.show = false"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import ImageLightbox from '@/Components/ImageLightbox.vue';
import MessageBubble from './MessageBubble.vue';
import MessageComposer from './MessageComposer.vue';
import { useConversationThread } from '@/composables/useConversationThread';
import { needsDateSeparator, dateSeparatorLabel } from '@/utils/messageTime';

const props = defineProps({
  loadUrl: { type: String, required: true },
  currentUserId: { type: Number, required: true },
});
const emit = defineEmits(['read']);

const confirm = useConfirm();
const toast = useToast();

const {
  conversation, messages, participants, hasMore, loading, loadingOlder, error, sending, typingUsers, readSignal,
  load, loadOlder, send, edit, remove, toggleReaction, markRead, notifyTyping,
} = useConversationThread({ currentUserId: props.currentUserId });

const scroller = ref(null);
const namesOpen = ref(false);
const editing = ref(null);
const editBody = ref('');
const lightbox = reactive({ show: false, images: [], index: 0 });

watch(readSignal, () => emit('read'));

function isAtBottom() {
  const el = scroller.value;
  if (!el) return true;
  return el.scrollHeight - el.scrollTop - el.clientHeight < 40;
}

function scrollToBottom() {
  nextTick(() => {
    const el = scroller.value;
    if (el) el.scrollTop = el.scrollHeight;
  });
}

// Newest message appended: follow it if we were already at the bottom or it is ours.
watch(() => messages.value.length, (len, prev) => {
  if (len <= prev) return;
  const last = messages.value[len - 1];
  if (last?.user_id === props.currentUserId || isAtBottom()) scrollToBottom();
});

async function reload() {
  await load(props.loadUrl);
  scrollToBottom();
  if (messages.value.length) markRead();
}

onMounted(reload);

async function loadOlderKeepingOffset() {
  const el = scroller.value;
  const before = el ? el.scrollHeight - el.scrollTop : 0;
  await loadOlder();
  await nextTick();
  if (el) el.scrollTop = el.scrollHeight - before;
}

function onScroll() {
  const el = scroller.value;
  if (el && el.scrollTop < 40 && hasMore.value && !loadingOlder.value) loadOlderKeepingOffset();
}

async function onSend(payload) {
  try {
    await send(payload);
    scrollToBottom();
  } catch (e) {
    const detail = e?.response?.data?.message || 'Could not send your comment. Please try again.';
    toast.add({ severity: 'error', summary: 'Not sent', detail, life: 4000 });
  }
}

function startEdit(m) {
  editing.value = m;
  editBody.value = m.body ?? '';
}

function cancelEdit() {
  editing.value = null;
  editBody.value = '';
}

function onEditKeydown(e) {
  if (e.isComposing || e.keyCode === 229) return;
  e.preventDefault();
  saveEdit();
}

async function saveEdit() {
  if (!editing.value || !editBody.value.trim()) return;
  try {
    await edit(editing.value.id, editBody.value.trim());
    cancelEdit();
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Edit failed', detail: e?.response?.data?.message || 'Please try again.', life: 4000 });
  }
}

function confirmDelete(m) {
  confirm.require({
    message: 'Delete this comment?',
    header: 'Delete comment',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await remove(m.id);
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Delete failed', detail: e?.response?.data?.message || 'Please try again.', life: 4000 });
      }
    },
  });
}

function openLightbox(m, index) {
  // ImageLightbox's `images` prop is an array of plain URL strings
  // (it assigns `currentImage.value = props.images[currentIndex.value]`
  // and binds it straight to `<img :src="currentImage">`), not objects.
  lightbox.images = m.attachments.map((a) =>
    route('chat.messages.attachments.show', { message: m.id, attachment: a.id }));
  lightbox.index = index;
  lightbox.show = true;
}

const typingLabel = computed(() => {
  const names = typingUsers.value.map((u) => u.name).filter(Boolean);
  if (!names.length) return '';
  if (names.length === 1) return `${names[0]} is typing…`;
  if (names.length === 2) return `${names[0]} and ${names[1]} are typing…`;
  return 'Several people are typing…';
});

const lastMessage = computed(() => messages.value[messages.value.length - 1] ?? null);

const seenBy = computed(() => {
  if (!lastMessage.value) return [];
  return participants.value.filter((p) =>
    p.user_id !== props.currentUserId
    && p.last_read_at
    && p.last_read_at >= lastMessage.value.created_at);
});

const seenSummary = computed(() => {
  if (!lastMessage.value || lastMessage.value.user_id !== props.currentUserId) return '';
  if (conversation.value?.type === 'dm') {
    const other = participants.value.find((p) => p.user_id !== props.currentUserId);
    if (other?.last_read_at && other.last_read_at >= lastMessage.value.created_at) return 'Seen';
    if (other?.last_delivered_at && other.last_delivered_at >= lastMessage.value.created_at) return 'Delivered';
    return '';
  }
  return seenBy.value.length ? `Seen by ${seenBy.value.length}` : '';
});

const seenNames = computed(() => seenBy.value.map((p) => p.name).filter(Boolean).join(', '));
</script>
