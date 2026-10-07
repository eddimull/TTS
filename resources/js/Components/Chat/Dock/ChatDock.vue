<template>
  <Teleport to="body">
    <div
      v-if="visible"
      ref="root"
      data-test="chat-dock"
      class="fixed bottom-0 right-4 z-40 hidden md:flex items-end gap-3 pointer-events-none"
    >
      <ChatDockWindow
        v-for="w in windowRows"
        :key="w.id"
        :conversation="w.conversation"
        :minimized="w.minimized"
        :current-user-id="userId"
        @close="dock.close(w.id)"
        @toggle="dock.toggleMinimize(w.id)"
        @read="onRead(w.id)"
      />

      <div class="flex flex-col items-end gap-3 pb-4">
        <div
          v-if="state.listOpen"
          data-test="dock-list"
          class="pointer-events-auto w-80 h-[28rem] rounded-lg shadow-2xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 overflow-hidden"
        >
          <ConversationList
            :conversations="state.conversations"
            :selected-id="null"
            :show-new="false"
            @select="dock.open"
          />
        </div>
        <ChatDockLauncher
          :count="store.state.user.chatUnread"
          :open="state.listOpen"
          @click="dock.toggleList"
        />
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useStore } from 'vuex';
import ConversationList from '@/Pages/Messages/Components/ConversationList.vue';
import { useChatDock } from '@/composables/useChatDock';
import ChatDockLauncher from './ChatDockLauncher.vue';
import ChatDockWindow from './ChatDockWindow.vue';

// Rendered once beside Inertia's root (app.js), so it survives every
// navigation — including pages that remount the authenticated layout.
// Never subscribes to Echo itself: list changes arrive through the Vuex
// `chatSignal` the layout maintains; each window's thread handles its own.
const props = defineProps({
  /** Injectable for tests; defaults to the app-wide singleton. */
  dock: { type: Object, default: null },
});

const dock = props.dock ?? useChatDock();
const { state, windowRows } = dock;
const page = usePage();
const store = useStore();
const root = ref(null);

const userId = computed(() => {
  const u = page.props.auth?.user;
  return u?.id && u.type !== 'contact' ? u.id : null;
});

// The Messages page shows every thread full-screen already.
const visible = computed(() => userId.value !== null && page.component !== 'Messages/Index');

watch(userId, (id) => {
  if (id === null) dock.reset();
  else if (state.userId !== id) dock.hydrate(id);
}, { immediate: true });

// Leaving for a page where the dock is hidden (Messages) shouldn't leave
// the popover open — or keep posting delivered acks — for when it returns.
watch(visible, (v) => {
  if (!v) dock.closeList();
});

watch(() => store.state.user.chatSignal, () => {
  if (userId.value !== null) dock.refreshList();
});

function onRead(id) {
  dock.markRead(id);
  store.dispatch('user/fetchChatUnread');
}

function onKeydown(e) {
  if (e.key === 'Escape' && state.listOpen) dock.closeList();
}

function onPointerDown(e) {
  if (state.listOpen && root.value && !root.value.contains(e.target)) dock.closeList();
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('mousedown', onPointerDown);
});
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  document.removeEventListener('mousedown', onPointerDown);
});
</script>
