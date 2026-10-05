<template>
  <Head title="Messages" />
  <Container class="md:container md:mx-auto bg-transparent dark:bg-transparent">
    <div class="max-w-6xl mx-auto px-0 sm:px-4 py-0 sm:py-4">
      <div
        class="h-[calc(100vh-8rem)] min-h-[32rem] rounded-none sm:rounded-lg overflow-hidden border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 md:grid md:grid-cols-[20rem_1fr]"
      >
        <!-- List pane -->
        <aside
          class="h-full min-h-0 border-r border-gray-200 dark:border-slate-600"
          :class="mobileShowThread ? 'hidden md:block' : 'block'"
        >
          <ConversationList
            :conversations="rows"
            :selected-id="selectedId"
            @select="select"
            @new="dialogOpen = true"
          />
        </aside>

        <!-- Thread pane -->
        <section
          class="h-full min-h-0 flex flex-col bg-gray-50 dark:bg-slate-900"
          :class="mobileShowThread ? 'flex' : 'hidden md:flex'"
        >
          <template v-if="selected">
            <header class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800">
              <button
                type="button"
                class="md:hidden p-1 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700"
                aria-label="Back to conversations"
                data-test="back"
                @click="mobileShowThread = false"
              >
                <i class="pi pi-arrow-left" />
              </button>
              <div class="min-w-0">
                <h2 class="truncate font-semibold text-gray-900 dark:text-gray-50">
                  {{ selected.title }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ subtitle }}
                </p>
              </div>
            </header>
            <ConversationThread
              :key="selected.id"
              :load-url="route('chat.conversations.messages.index', selected.id)"
              :current-user-id="currentUserId"
              class="flex-1 min-h-0"
              @read="onRead(selected.id)"
            />
          </template>
          <div
            v-else
            class="flex-1 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400"
          >
            Select a conversation
          </div>
        </section>
      </div>
    </div>

    <NewMessageDialog
      v-model:visible="dialogOpen"
      @created="onCreated"
    />
  </Container>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useStore } from 'vuex';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import ConversationThread from '@/Components/Chat/ConversationThread.vue';
import ConversationList from './Components/ConversationList.vue';
import NewMessageDialog from './Components/NewMessageDialog.vue';

defineOptions({ layout: BreezeAuthenticatedLayout });

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  initialConversationId: { type: Number, default: null },
});

const page = usePage();
const store = useStore();
const toast = useToast();
let refreshToasted = false;

const currentUserId = computed(() => page.props.auth?.user?.id);
const rows = ref([...props.conversations]);
const selectedId = ref(props.initialConversationId);
const mobileShowThread = ref(props.initialConversationId !== null);
const dialogOpen = ref(false);

const selected = computed(() => rows.value.find((c) => c.id === selectedId.value) ?? null);

const subtitle = computed(() => {
  const c = selected.value;
  if (!c) return '';
  if (c.type === 'dm') return 'Direct message';
  if (c.type === 'band') return 'Band channel';
  return { booking: 'Booking thread', event: 'Event thread', rehearsal: 'Rehearsal thread' }[c.topic_type] ?? 'Thread';
});

function select(id) {
  selectedId.value = id;
  mobileShowThread.value = true;
  if (typeof window !== 'undefined') {
    window.history.replaceState(window.history.state, '', route('messages.index', id));
  }
}

// Keep rows in sync with the server; "delivered" means "my inbox has
// everything up to now" — same hook the mobile app fires on list fetch.
async function refreshList() {
  try {
    const { data } = await axios.get(route('chat.conversations.index'));
    rows.value = data.conversations ?? rows.value;
    refreshToasted = false;
  } catch (e) {
    // keep the current rows; the next signal retries
    if (!refreshToasted) {
      refreshToasted = true;
      toast.add({ severity: 'warn', summary: 'Could not refresh messages', detail: 'Showing the last known list. It will retry on the next update.', life: 5000 });
    }
  }
  axios.post(route('chat.conversations.delivered')).catch(() => {});
}

onMounted(() => {
  axios.post(route('chat.conversations.delivered')).catch(() => {});
});

watch(() => store.state.user.chatSignal, () => refreshList());

function onRead(id) {
  const i = rows.value.findIndex((c) => c.id === id);
  if (i !== -1) rows.value.splice(i, 1, { ...rows.value[i], unread_count: 0 });
  store.dispatch('user/fetchChatUnread');
}

function onCreated(conversation) {
  const i = rows.value.findIndex((c) => c.id === conversation.id);
  if (i === -1) rows.value = [conversation, ...rows.value];
  else rows.value.splice(i, 1, conversation);
  select(conversation.id);
}
</script>
