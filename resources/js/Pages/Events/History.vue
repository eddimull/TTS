<template>
  <Container class="md:container md:mx-auto">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 py-6">
      <!-- Header Section -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <Link
                :href="route('events')"
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition-colors"
              >
                <i class="pi pi-arrow-left text-lg" />
              </Link>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-50">
                Event History
              </h1>
            </div>
            
            <div class="space-y-2 mt-4">
              <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                <i class="pi pi-calendar text-blue-500" />
                <span class="font-semibold">{{ event.title }}</span>
              </div>
              
              <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                <div class="flex items-center gap-2">
                  <i class="pi pi-clock" />
                  <span>{{ formatDate(event.date) }} at {{ formatTime(event.time) }}</span>
                </div>
                
                <div class="flex items-center gap-2">
                  <i class="pi pi-users" />
                  <span>{{ event.band_name }}</span>
                </div>
                
                <div class="flex items-center gap-2">
                  <i class="pi pi-tag" />
                  <span>{{ event.event_type }}</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="flex gap-2">
            <Link
              :href="route('events.advance', event.key)"
              class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            >
              <i class="pi pi-eye mr-2" />
              View Event
            </Link>
          </div>
        </div>
      </div>

      <!-- Statistics Section -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-4">
          <div class="flex items-center gap-3">
            <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-lg">
              <i class="pi pi-list text-blue-600 dark:text-blue-300 text-xl" />
            </div>
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-gray-50">
                {{ activities.length }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                Total Activities
              </div>
            </div>
          </div>
        </div>
        
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-4">
          <div class="flex items-center gap-3">
            <div class="bg-green-100 dark:bg-green-900 p-3 rounded-lg">
              <i class="pi pi-plus text-green-600 dark:text-green-300 text-xl" />
            </div>
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-gray-50">
                {{ getActivityCount('created') }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                Created
              </div>
            </div>
          </div>
        </div>
        
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-4">
          <div class="flex items-center gap-3">
            <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-lg">
              <i class="pi pi-pencil text-purple-600 dark:text-purple-300 text-xl" />
            </div>
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-gray-50">
                {{ getActivityCount('updated') }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                Updates
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Timeline Section -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
        <div class="flex items-center gap-3 mb-6">
          <i class="pi pi-history text-2xl text-gray-700 dark:text-gray-300" />
          <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-50">
            Activity Timeline
          </h2>
        </div>
        
        <ActivityTimeline :activities="activities" />
      </div>
    </div>
  </Container>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Container from '@/Components/Container.vue';
import ActivityTimeline from '@/Components/ActivityTimeline.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { DateTime } from 'luxon';

defineOptions({
    layout: BreezeAuthenticatedLayout,
});

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    activities: {
        type: Array,
        required: true,
        default: () => [],
    },
});

// Helper methods
const formatDate = (date) => {
    return DateTime.fromISO(date).toFormat('LLLL dd, yyyy');
};

const formatTime = (time) => {
    return DateTime.fromFormat(time, 'HH:mm:ss').toFormat('h:mm a');
};

const getActivityCount = (type) => {
    return props.activities.filter(activity => activity.event_type === type).length;
};
</script>
