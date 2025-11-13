<template>
  <Container>
    <div class="space-y-6">
      <!-- Header Section -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <Link
                :href="route('Bookings Home')"
                class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
              >
                <i class="pi pi-arrow-left" />
              </Link>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-50">
                Booking History
              </h1>
            </div>
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-2">
              {{ booking.name }}
            </h2>
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
              <div class="flex items-center gap-2">
                <i class="pi pi-calendar" />
                <span>{{ formatDate(booking.date) }} at {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}</span>
              </div>
              <div class="flex items-center gap-2">
                <i class="pi pi-building" />
                <span>{{ booking.venue_name || 'TBD' }}</span>
              </div>
              <div class="flex items-center gap-2">
                <i class="pi pi-users" />
                <span>{{ booking.band_name }}</span>
              </div>
              <div class="flex items-center gap-2">
                <i class="pi pi-tag" />
                <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs font-semibold">
                  {{ booking.event_type }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <i
                  class="pi pi-circle-fill text-xs"
                  :class="getStatusColor(booking.status)"
                />
                <span class="capitalize">{{ booking.status }}</span>
              </div>
            </div>
          </div>
          <div class="flex gap-2">
            <Link
              :href="route('Booking Details', { band: band.id, booking: booking.id })"
              class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
            >
              <i class="pi pi-eye" />
              <span>View Booking</span>
            </Link>
          </div>
        </div>
      </div>

      <!-- Statistics Section -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
          <div class="flex items-center gap-4">
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

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
          <div class="flex items-center gap-4">
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

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
          <div class="flex items-center gap-4">
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

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm p-6">
          <div class="flex items-center gap-4">
            <div class="bg-amber-100 dark:bg-amber-900 p-3 rounded-lg">
              <i class="pi pi-dollar text-amber-600 dark:text-amber-300 text-xl" />
            </div>
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-gray-50">
                ${{ booking.amount_paid }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                Paid of ${{ booking.price }} 
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
    booking: {
        type: Object,
        required: true,
    },
    band: {
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
    // Time comes as HH:mm format from backend
    return DateTime.fromFormat(time, 'HH:mm').toFormat('h:mm a');
};

const getActivityCount = (type) => {
    return props.activities.filter(activity => activity.event_type === type).length;
};

const getStatusColor = (status) => {
    const colors = {
        'confirmed': 'text-green-600 dark:text-green-400',
        'pending': 'text-yellow-600 dark:text-yellow-400',
        'cancelled': 'text-red-600 dark:text-red-400',
        'draft': 'text-gray-600 dark:text-gray-400',
    };
    return colors[status] || 'text-gray-600 dark:text-gray-400';
};
</script>
