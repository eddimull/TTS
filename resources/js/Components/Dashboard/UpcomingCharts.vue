<template>
  <div v-if="charts.length > 0">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl border-2 border-green-200 dark:border-green-800">
      <!-- Header -->
      <div
        class="px-4 py-3 bg-gradient-to-r from-green-50 to-blue-50 dark:from-green-900/20 dark:to-blue-900/20 cursor-pointer hover:from-green-100 hover:to-blue-100 dark:hover:from-green-900/30 dark:hover:to-blue-900/30 transition-colors"
        :class="{ 'border-b border-gray-200 dark:border-gray-700': isExpanded }"
        @click="toggleExpand"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              Upcoming Music to Practice
            </h3>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-sm text-gray-600 dark:text-gray-400">
              {{ charts.length }} item{{ charts.length !== 1 ? 's' : '' }} in {{ uniqueEventCount }} event{{ uniqueEventCount !== 1 ? 's' : '' }}
            </span>
            <svg
              class="w-5 h-5 text-gray-500 dark:text-gray-400 transition-transform duration-200"
              :class="{ 'rotate-180': isExpanded }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Charts List -->
      <div v-show="isExpanded" class="divide-y divide-gray-200 dark:divide-gray-700">
        <div
          v-for="(chart, index) in charts"
          :key="`chart-${index}`"
          class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors"
        >
          <div class="flex items-start justify-between gap-4">
            <!-- Chart/Song Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-baseline gap-2 mb-1">
                <!-- Type Badge -->
                <span
                  class="text-xs font-medium px-2 py-0.5 rounded"
                  :class="chart.type === 'chart' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'"
                >
                  {{ chart.type === 'chart' ? 'Chart' : 'Song' }}
                </span>

                <!-- For charts: link to chart detail page -->
                <a
                  v-if="chart.type === 'chart'"
                  :href="route('charts.show', chart.chart_id)"
                  class="font-medium text-green-700 dark:text-green-300 hover:text-green-900 dark:hover:text-green-100 hover:underline"
                >
                  {{ chart.title }}
                </a>

                <!-- For songs: external link -->
                <a
                  v-else
                  :href="chart.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-medium text-blue-700 dark:text-blue-300 hover:text-blue-900 dark:hover:text-blue-100 hover:underline inline-flex items-center gap-1"
                >
                  {{ chart.title }}
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>

                <span
                  v-if="chart.composer"
                  class="text-sm text-gray-600 dark:text-gray-400"
                >
                  by {{ chart.composer }}
                </span>
              </div>

              <!-- Event Info -->
              <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>{{ formatEventDate(chart.event_date) }}</span>
                <span class="text-gray-400 dark:text-gray-500">•</span>
                <a
                  href="#"
                  class="hover:underline text-blue-600 dark:text-blue-400"
                  @click.prevent="scrollToEvent(chart.event_id)"
                >
                  {{ chart.event_title }}
                </a>
                <span v-if="chart.venue_name" class="text-gray-400 dark:text-gray-500">•</span>
                <span v-if="chart.venue_name" class="truncate">{{ chart.venue_name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Empty State (when no upcoming charts) -->
  <div
    v-else
    class="bg-gray-50 dark:bg-slate-800/50 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-6 text-center"
  >
    <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
    <p class="text-gray-600 dark:text-gray-400 text-sm">
      No songs or charts in your upcoming events
    </p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { DateTime } from 'luxon';

const props = defineProps({
  charts: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['scroll-to-event']);

const isExpanded = ref(true);

const toggleExpand = () => {
  isExpanded.value = !isExpanded.value;
};

const uniqueEventCount = computed(() => {
  const uniqueEvents = new Set(props.charts.map(c => c.event_id));
  return uniqueEvents.size;
});

const formatEventDate = (dateString) => {
  const date = DateTime.fromFormat(dateString, 'yyyy-MM-dd');
  return date.toFormat('EEE, MMM d');
};

const scrollToEvent = (eventId) => {
  emit('scroll-to-event', eventId);
};
</script>
