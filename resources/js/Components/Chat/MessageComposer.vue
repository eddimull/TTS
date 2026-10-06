<template>
  <div class="border-t border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 p-2">
    <!-- Attachment previews -->
    <div
      v-if="files.length"
      class="flex gap-2 mb-2 overflow-x-auto"
    >
      <div
        v-for="(f, i) in files"
        :key="f.key"
        data-test="preview"
        class="relative shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-slate-700"
      >
        <img
          v-if="f.url"
          :src="f.url"
          class="w-full h-full object-cover"
          alt=""
        >
        <button
          type="button"
          class="absolute top-0.5 right-0.5 bg-black/60 text-white rounded-full w-5 h-5 text-xs leading-5"
          aria-label="Remove image"
          @click="removeFile(i)"
        >
          ×
        </button>
      </div>
    </div>
    <p
      v-if="capNotice"
      class="text-xs text-amber-600 dark:text-amber-400 mb-1"
    >
      Up to {{ maxImages }} images per {{ noun }}.
    </p>

    <div class="flex items-end gap-2">
      <label
        class="p-2 rounded-full cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        :class="{ 'opacity-50 pointer-events-none': disabled || files.length >= maxImages }"
        aria-label="Attach image"
      >
        <i class="pi pi-image text-lg" />
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          class="hidden"
          :disabled="disabled"
          @change="onPick"
        >
      </label>

      <textarea
        ref="textareaRef"
        v-model="body"
        rows="1"
        :disabled="disabled"
        :placeholder="`Add a ${noun}…`"
        class="flex-1 resize-none max-h-40 rounded-2xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-50 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        @input="onInput"
        @keydown="onKeydown"
        @paste="onPaste"
      />

      <Button
        data-test="send"
        icon="pi pi-send"
        rounded
        :disabled="!canSend"
        aria-label="Send"
        @click="submit"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
  disabled: { type: Boolean, default: false },
  maxImages: { type: Number, default: 4 },
  /** 'comment' (topic threads) or 'message' (DMs / band chat) — copy only. */
  noun: { type: String, default: 'comment' },
});
const emit = defineEmits(['send', 'typing']);

const body = ref('');
const files = ref([]); // [{ file, url, key }]
const capNotice = ref(false);
const textareaRef = ref(null);
let keyCounter = 0;

const canSend = computed(() => !props.disabled && (body.value.trim() !== '' || files.value.length > 0));

function revokePreview(url) {
  if (url && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') URL.revokeObjectURL(url);
}

function previewUrl(file) {
  return typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : '';
}

function addFiles(list) {
  const incoming = Array.from(list || []).filter((f) => f.type?.startsWith('image/'));
  const room = props.maxImages - files.value.length;
  capNotice.value = incoming.length > room;
  incoming.slice(0, Math.max(0, room)).forEach((file) => {
    files.value.push({ file, url: previewUrl(file), key: `f${keyCounter++}` });
  });
}

function onPick(e) {
  if (props.disabled) return;
  addFiles(e.target.files);
  e.target.value = '';
}

function onPaste(e) {
  if (props.disabled) return;
  const items = Array.from(e.clipboardData?.items || []).filter((i) => i.type.startsWith('image/'));
  if (!items.length) return;
  e.preventDefault();
  addFiles(items.map((i) => i.getAsFile()).filter(Boolean));
}

function removeFile(i) {
  const [removed] = files.value.splice(i, 1);
  revokePreview(removed?.url);
  capNotice.value = false;
}

function autosize() {
  const el = textareaRef.value;
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
}

function onInput() {
  autosize();
  if (body.value.trim() !== '') emit('typing');
}

function onKeydown(e) {
  if (props.disabled) return;
  if (e.isComposing || e.keyCode === 229) return;
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    submit();
  }
}

function submit() {
  if (!canSend.value) return;
  emit('send', { body: body.value.trim(), files: files.value.map((f) => f.file) });
  files.value.forEach((f) => revokePreview(f.url));
  body.value = '';
  files.value = [];
  capNotice.value = false;
  nextTick(autosize);
}
</script>
