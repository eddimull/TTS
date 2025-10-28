<template>
  <div
    class="sticky bottom-0 left-0 right-0 bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-600 py-4 px-3 md:px-6 -mx-2 md:-mx-6 -mb-2 md:-mb-6 rounded-b-xl shadow-lg w-[calc(100%+1rem)] md:w-[calc(100%+3rem)]"
    style="z-index: 9999;"
  >
    <!-- Mobile layout (xs to md screens) -->
    <div class="flex flex-col gap-3 md:hidden">
      <!-- Primary actions row -->
      <div class="flex gap-2">
        <button
          class="flex-1 px-3 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-slate-600 dark:hover:bg-slate-500 text-gray-800 dark:text-gray-50 rounded-lg font-medium transition-colors shadow-sm hover:shadow text-sm"
          @click="$emit('cancel')"
        >
          Cancel
        </button>
        <button
          :disabled="isSaving"
          :class="[
            'flex-1 px-3 py-2 rounded-lg font-medium transition-colors flex flex-col items-center justify-center shadow-sm hover:shadow text-sm',
            isSaving ? 'bg-blue-400 cursor-wait' : 'bg-blue-500 hover:bg-blue-600'
          ]"
          class="text-white"
          @click="$emit('save')"
        >
          <div class="flex items-center gap-2">
            <svg
              v-if="!isSaving"
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
            </svg>
            <i
              v-else
              class="pi pi-spin pi-spinner"
            />
            <span>{{ isSaving ? 'Saving...' : 'Save Changes' }}</span>
          </div>
          <span
            v-if="lastSaved && !isSaving"
            class="text-xs opacity-80 mt-1"
          >
            {{ lastSavedText }}
          </span>
        </button>
      </div>
      <!-- Secondary actions row -->
      <div class="flex gap-2">
        <button
          class="flex-1 px-3 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2 shadow-sm hover:shadow text-sm"
          @click="$emit('viewOnDashboard')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
          </svg>
          View Dashboard
        </button>
        <button
          class="flex-1 px-3 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2 shadow-sm hover:shadow text-sm"
          @click="$emit('viewHistory')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
              clip-rule="evenodd"
            />
          </svg>
          History
        </button>
        <button
          class="flex-1 px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2 shadow-sm hover:shadow text-sm"
          @click="$emit('removeEvent')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
              clip-rule="evenodd"
            />
          </svg>
          Remove Event
        </button>
      </div>
    </div>

    <!-- Desktop layout (md screens and up) -->
    <div class="hidden md:flex justify-between items-center gap-1 lg:gap-2 max-w-full">
      <button
        class="px-2 lg:px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition-colors flex items-center gap-1 lg:gap-2 shadow-sm hover:shadow whitespace-nowrap text-sm lg:text-base"
        @click="$emit('removeEvent')"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-4 w-4 lg:h-5 lg:w-5 flex-shrink-0"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
            clip-rule="evenodd"
          />
        </svg>
        <span class="hidden lg:inline">Remove Event</span>
        <span class="lg:hidden">Remove</span>
      </button>
      <div class="flex gap-1 lg:gap-2 items-center flex-wrap justify-end min-w-0">
        <button
          class="px-2 lg:px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg font-medium transition-colors flex items-center gap-1 lg:gap-2 shadow-sm hover:shadow whitespace-nowrap text-sm lg:text-base"
          @click="$emit('viewHistory')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4 lg:h-5 lg:w-5"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
              clip-rule="evenodd"
            />
          </svg>
          History
        </button>
        <button
          class="px-2 lg:px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg font-medium transition-colors flex items-center gap-1 lg:gap-2 shadow-sm hover:shadow whitespace-nowrap text-sm lg:text-base"
          @click="$emit('viewOnDashboard')"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4 lg:h-5 lg:w-5 flex-shrink-0"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
          </svg>
          Dashboard
        </button>
        <button
          class="px-2 lg:px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-slate-600 dark:hover:bg-slate-500 text-gray-800 dark:text-gray-50 rounded-lg font-medium transition-colors shadow-sm hover:shadow whitespace-nowrap text-sm lg:text-base"
          @click="$emit('cancel')"
        >
          Cancel
        </button>
        <button
          :disabled="isSaving"
          :class="[
            'px-2 lg:px-4 py-2 rounded-lg font-medium transition-colors flex flex-col items-center gap-1 shadow-sm hover:shadow whitespace-nowrap text-sm lg:text-base',
            isSaving ? 'bg-blue-400 cursor-wait' : 'bg-blue-500 hover:bg-blue-600'
          ]"
          class="text-white"
          @click="$emit('save')"
        >
          <div class="flex items-center gap-1 lg:gap-2">
            <svg
              v-if="!isSaving"
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4 lg:h-5 lg:w-5 flex-shrink-0"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
            </svg>
            <i
              v-else
              class="pi pi-spin pi-spinner flex-shrink-0 text-sm lg:text-base"
            />
            <span>{{ isSaving ? 'Saving...' : 'Save Changes' }}</span>
          </div>
          <span
            v-if="lastSaved && !isSaving"
            class="text-xs opacity-80"
          >
            {{ lastSavedText }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { DateTime } from 'luxon';

const props = defineProps({
  isSaving: {
    type: Boolean,
    default: false
  },
  lastSaved: {
    type: [String, Number],
    default: null
  }
});

defineEmits(["save", "cancel", "removeEvent", "viewOnDashboard", "viewHistory"]);

// Format last saved time in relative format
const lastSavedText = computed(() => {
  if (!props.lastSaved) return '';
  
  // Handle both timestamp (number) and ISO string
  let saved;
  if (typeof props.lastSaved === 'number') {
    saved = DateTime.fromMillis(props.lastSaved);
  } else {
    saved = DateTime.fromISO(props.lastSaved);
  }
  
  const now = DateTime.now();
  const diffInSeconds = now.diff(saved, 'seconds').seconds;
  
  if (diffInSeconds < 60) {
    return 'Saved just now';
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60);
    return `Saved ${minutes}m ago`;
  } else {
    const hours = Math.floor(diffInSeconds / 3600);
    return `Saved ${hours}h ago`;
  }
});
</script>
