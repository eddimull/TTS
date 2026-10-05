<template>
  <div
    class="group flex flex-col"
    :class="isOwn ? 'items-end' : 'items-start'"
    :data-message-id="message.id"
  >
    <div
      v-if="!isOwn"
      class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5 px-1"
    >
      {{ message.user_name }}
    </div>

    <div class="relative max-w-[85%] flex items-end gap-1" :class="isOwn ? 'flex-row-reverse' : ''">
      <!-- Bubble -->
      <div
        class="rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap break-words"
        :class="bubbleClass"
        :title="timeLabel"
      >
        <span v-if="message.is_deleted" class="italic opacity-70">Message deleted</span>
        <template v-else>
          <div
            v-if="message.attachments.length"
            class="grid gap-1 mb-1"
            :class="message.attachments.length > 1 ? 'grid-cols-2' : 'grid-cols-1'"
          >
            <button
              v-for="(att, i) in message.attachments"
              :key="att.id"
              type="button"
              class="block rounded-lg overflow-hidden bg-black/5 dark:bg-white/10"
              @click="$emit('open-attachment', i)"
            >
              <img
                :src="attachmentUrl(att)"
                :width="att.width || undefined"
                :height="att.height || undefined"
                class="max-h-64 w-auto object-cover"
                alt=""
                loading="lazy"
              >
            </button>
          </div>
          <span v-if="message.body">{{ message.body }}</span>
          <span
            v-if="message.edited_at"
            class="ml-1 text-[10px] opacity-70"
          >(edited)</span>
        </template>
      </div>

      <!-- Hover actions -->
      <div
        v-if="!message.is_deleted"
        class="opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity flex items-center gap-0.5 text-gray-500 dark:text-gray-400"
      >
        <button
          type="button"
          class="p-1 rounded hover:bg-gray-100 dark:hover:bg-slate-700"
          aria-label="Add reaction"
          @click.stop="pickerOpen = !pickerOpen"
        >
          <i class="pi pi-face-smile text-sm" />
        </button>
        <button
          v-if="canEdit"
          type="button"
          class="p-1 rounded hover:bg-gray-100 dark:hover:bg-slate-700"
          aria-label="Edit"
          @click="$emit('edit')"
        >
          <i class="pi pi-pencil text-sm" />
        </button>
        <button
          v-if="canDelete"
          type="button"
          class="p-1 rounded hover:bg-gray-100 dark:hover:bg-slate-700"
          aria-label="Delete"
          @click="$emit('delete')"
        >
          <i class="pi pi-trash text-sm" />
        </button>
      </div>

      <div
        v-if="pickerOpen"
        class="absolute -top-10 z-10"
        :class="isOwn ? 'right-0' : 'left-0'"
      >
        <ReactionPicker @pick="onPick" />
      </div>
    </div>

    <!-- Reaction chips -->
    <div
      v-if="message.reactions?.length"
      class="flex flex-wrap gap-1 mt-1 px-1"
    >
      <button
        v-for="r in message.reactions"
        :key="r.emoji"
        type="button"
        class="text-xs rounded-full px-2 py-0.5 border transition-colors"
        :class="r.user_ids.includes(currentUserId)
          ? 'bg-blue-100 border-blue-300 text-blue-900 dark:bg-blue-900 dark:border-blue-600 dark:text-blue-100'
          : 'bg-gray-100 border-gray-200 text-gray-700 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200'"
        :aria-label="`${r.emoji} ${r.count}`"
        @click="$emit('react', r.emoji)"
      >
        {{ r.emoji }} {{ r.count }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import ReactionPicker from './ReactionPicker.vue';
import { bubbleTimeLabel } from '@/utils/messageTime';

const props = defineProps({
  message: { type: Object, required: true },
  isOwn: { type: Boolean, default: false },
  canModerate: { type: Boolean, default: false },
  currentUserId: { type: Number, required: true },
});
const emit = defineEmits(['react', 'edit', 'delete', 'open-attachment']);

const pickerOpen = ref(false);

// Edit is author-only and (as on mobile) hidden for messages with images.
const canEdit = computed(() => props.isOwn && props.message.attachments.length === 0);
const canDelete = computed(() => props.isOwn || props.canModerate);
const timeLabel = computed(() => bubbleTimeLabel(props.message.created_at));

const bubbleClass = computed(() => props.isOwn
  ? 'bg-blue-600 text-white rounded-br-md'
  : 'bg-gray-100 text-gray-900 dark:bg-slate-700 dark:text-gray-50 rounded-bl-md');

function attachmentUrl(att) {
  return route('chat.messages.attachments.show', { message: props.message.id, attachment: att.id });
}

function onPick(emoji) {
  pickerOpen.value = false;
  emit('react', emoji);
}
</script>
