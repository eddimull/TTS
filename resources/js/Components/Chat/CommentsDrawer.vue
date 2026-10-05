<template>
  <Drawer
    :visible="visible"
    position="right"
    class="!w-full md:!w-[28rem]"
    :pt="{ content: { class: 'flex flex-col p-0 min-h-0' } }"
    @update:visible="$emit('update:visible', $event)"
  >
    <template #header>
      <div class="flex items-center gap-2 min-w-0">
        <i class="pi pi-comments text-gray-500 dark:text-gray-400" />
        <span class="font-semibold truncate text-gray-900 dark:text-gray-50">{{ title || 'Comments' }}</span>
      </div>
    </template>

    <ConversationThread
      v-if="visible"
      :load-url="loadUrl"
      :current-user-id="currentUserId"
      class="flex-1 min-h-0"
      @read="$emit('read')"
    />
  </Drawer>
</template>

<script setup>
import Drawer from 'primevue/drawer';
import ConversationThread from './ConversationThread.vue';

defineProps({
  visible: { type: Boolean, default: false },
  title: { type: String, default: 'Comments' },
  loadUrl: { type: String, required: true },
  currentUserId: { type: Number, required: true },
});
defineEmits(['update:visible', 'read']);
</script>
