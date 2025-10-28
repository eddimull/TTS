<template>
  <Dialog
    v-model:visible="localVisible"
    modal
    :header="modalTitle"
    :style="{ width: '90vw', maxWidth: '800px' }"
    :breakpoints="{ '960px': '90vw', '640px': '95vw' }"
    :draggable="false"
    class="activity-history-modal"
  >
    <template #header>
      <div class="flex items-center gap-3">
        <i class="pi pi-history text-2xl text-blue-600 dark:text-blue-400" />
        <div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-gray-50">
            Event History
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ eventTitle }}
          </p>
        </div>
      </div>
    </template>

    <!-- Loading State -->
    <div
      v-if="loading"
      class="flex flex-col items-center justify-center py-12"
    >
      <i class="pi pi-spin pi-spinner text-4xl text-blue-500 mb-4" />
      <p class="text-gray-600 dark:text-gray-400">
        Loading activity history...
      </p>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="flex flex-col items-center justify-center py-12"
    >
      <i class="pi pi-exclamation-circle text-4xl text-red-500 mb-4" />
      <p class="text-red-600 dark:text-red-400 mb-2">
        Failed to load history
      </p>
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ error }}
      </p>
    </div>

    <!-- Content -->
    <div
      v-else
      class="max-h-[60vh] overflow-y-auto"
    >
      <!-- Statistics -->
      <div
        v-if="activities.length > 0"
        class="grid grid-cols-3 gap-3 mb-6 p-4 bg-gray-50 dark:bg-slate-700 rounded-lg"
      >
        <div class="text-center">
          <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
            {{ activities.length }}
          </div>
          <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
            Total
          </div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-green-600 dark:text-green-400">
            {{ getActivityCount('created') }}
          </div>
          <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
            Created
          </div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
            {{ getActivityCount('updated') }}
          </div>
          <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
            Updates
          </div>
        </div>
      </div>

      <!-- Timeline -->
      <ActivityTimeline :activities="activities" />
    </div>

    <template #footer>
      <div class="flex justify-between items-center">
        <a
          v-if="eventKey"
          :href="route('events.history', eventKey)"
          target="_blank"
          class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-2"
        >
          <i class="pi pi-external-link" />
          Open in new tab
        </a>
        <div class="flex-1" />
        <Button
          label="Close"
          severity="secondary"
          @click="localVisible = false"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import ActivityTimeline from './ActivityTimeline.vue';
import axios from 'axios';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    eventKey: {
        type: String,
        required: true,
    },
    eventTitle: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:visible']);

const localVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const loading = ref(false);
const error = ref(null);
const activities = ref([]);

const modalTitle = computed(() => {
    return props.eventTitle ? `History: ${props.eventTitle}` : 'Event History';
});

const getActivityCount = (type) => {
    return activities.value.filter(activity => activity.event_type === type).length;
};

// Fetch activities when modal opens
watch(() => props.visible, async (newValue) => {
    if (newValue && props.eventKey) {
        await fetchActivities();
    }
});

const fetchActivities = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        // Fetch from the JSON endpoint
        const response = await axios.get(route('events.historyJson', props.eventKey));
        
        if (response.data && response.data.activities) {
            activities.value = response.data.activities;
        } else {
            throw new Error('Invalid response format');
        }
    } catch (err) {
        console.error('Error fetching activities:', err);
        error.value = err.response?.data?.message || err.message || 'An error occurred';
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.activity-history-modal :deep(.p-dialog-content) {
    padding: 0 1.5rem 1rem 1.5rem;
}

.activity-history-modal :deep(.p-dialog-header) {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
}
</style>
