<template>
  <Dialog
    :visible="visible"
    modal
    header="New message"
    :style="{ width: '28rem', maxWidth: '95vw' }"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="space-y-3">
      <input
        v-model="query"
        type="search"
        placeholder="Search people"
        aria-label="Search people"
        class="w-full px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >

      <p
        v-if="error"
        class="text-sm text-red-600 dark:text-red-400"
      >
        {{ error }}
      </p>

      <div class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-slate-700">
        <p
          v-if="loading"
          class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center"
        >
          Loading people…
        </p>
        <p
          v-else-if="!filtered.length"
          class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center"
        >
          No one matches.
        </p>
        <button
          v-for="c in filtered"
          :key="c.id"
          type="button"
          data-test="contact-row"
          class="w-full text-left flex items-center justify-between gap-3 px-2 py-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md disabled:opacity-50"
          :disabled="creating"
          @click="choose(c)"
        >
          <span class="min-w-0">
            <span class="block truncate font-medium text-gray-900 dark:text-gray-50">{{ c.name }}</span>
            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ c.context }}</span>
          </span>
          <Tag
            v-if="c.is_sub"
            value="Sub"
            severity="secondary"
            rounded
          />
        </button>
      </div>
    </div>
  </Dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  visible: { type: Boolean, default: false },
});
const emit = defineEmits(['update:visible', 'created']);

const contacts = ref([]);
const query = ref('');
const loading = ref(false);
const creating = ref(false);
const error = ref('');

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(route('chat.contacts'));
    contacts.value = data.contacts ?? [];
  } catch (e) {
    error.value = 'Could not load people. Please try again.';
  } finally {
    loading.value = false;
  }
}

watch(() => props.visible, (v) => {
  if (v) {
    query.value = '';
    load();
  }
}, { immediate: true });

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  return q ? contacts.value.filter((c) => c.name.toLowerCase().includes(q)) : contacts.value;
});

async function choose(contact) {
  creating.value = true;
  error.value = '';
  try {
    const { data } = await axios.post(route('chat.conversations.dm'), { user_id: contact.id });
    emit('created', data.conversation);
    emit('update:visible', false);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Could not start the conversation.';
  } finally {
    creating.value = false;
  }
}
</script>
