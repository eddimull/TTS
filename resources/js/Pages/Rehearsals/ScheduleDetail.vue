<template>
  <BreezeAuthenticatedLayout>
    <Container class="dark:bg-slate-600 md:container md:mx-auto">
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div class="componentPanel overflow-auto shadow-sm sm:rounded-lg">
            <div class="p-6">
              <!-- Header -->
              <div class="flex justify-between items-start mb-6">
                <div>
                  <div class="flex items-center gap-3 mb-2">
                    <h2 class="text-3xl font-bold">
                      {{ schedule.name }}
                    </h2>
                    <span
                      v-if="schedule.active"
                      class="px-3 py-1 bg-green-500 text-white text-sm rounded-full"
                    >
                      Active
                    </span>
                    <span
                      v-else
                      class="px-3 py-1 bg-gray-500 text-white text-sm rounded-full"
                    >
                      Inactive
                    </span>
                  </div>
                  <Link
                    :href="route('rehearsal-schedules.index', { band: band.id })"
                    class="text-blue-500 hover:text-blue-700 text-sm"
                  >
                    ← Back to All Schedules
                  </Link>
                </div>
                <div class="flex gap-2">
                  <Link
                    v-if="canWrite"
                    :href="route('rehearsals.create', { band: band.id, rehearsal_schedule: schedule.id })"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
                  >
                    Add Rehearsal
                  </Link>
                  <Link
                    v-if="canWrite"
                    :href="route('rehearsal-schedules.edit', { band: band.id, rehearsal_schedule: schedule.id })"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                  >
                    Edit Schedule
                  </Link>
                </div>
              </div>

              <!-- Schedule Details -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div v-if="schedule.description">
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Description
                  </h4>
                  <p class="text-gray-600 dark:text-gray-400">
                    {{ schedule.description }}
                  </p>
                </div>

                <div>
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Frequency
                  </h4>
                  <p class="text-gray-600 dark:text-gray-400 capitalize">
                    {{ schedule.frequency }}
                  </p>
                </div>

                <div v-if="schedule.selected_days && schedule.selected_days.length > 0">
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Day(s) of Week
                  </h4>
                  <p class="text-gray-600 dark:text-gray-400 capitalize">
                    {{ formatDaysOfWeek(schedule.selected_days) }}
                  </p>
                </div>
                <div v-else-if="schedule.day_of_week">
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Day of Week
                  </h4>
                  <p class="text-gray-600 dark:text-gray-400 capitalize">
                    {{ schedule.day_of_week }}
                  </p>
                </div>

                <!-- Monthly Pattern Details -->
                <div v-if="schedule.frequency === 'monthly' && schedule.monthly_pattern">
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Monthly Schedule
                  </h4>
                  <p class="text-gray-600 dark:text-gray-400">
                    {{ formatMonthlyPattern(schedule) }}
                  </p>
                </div>

                <div v-if="schedule.default_time">
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Default Time
                  </h4>
                  <p class="text-gray-600 dark:text-gray-400">
                    {{ formatTime(schedule.default_time) }}
                  </p>
                </div>

                <div v-if="schedule.location_name">
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Default Location
                  </h4>
                  <p class="text-gray-600 dark:text-gray-400">
                    {{ schedule.location_name }}
                  </p>
                  <p
                    v-if="schedule.location_address"
                    class="text-sm text-gray-500 dark:text-gray-500 mt-1"
                  >
                    {{ schedule.location_address }}
                  </p>
                </div>

                <div v-if="schedule.notes">
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Notes
                  </h4>
                  <p class="text-gray-600 dark:text-gray-400 whitespace-pre-wrap">
                    {{ schedule.notes }}
                  </p>
                </div>
              </div>

              <!-- Rehearsals List -->
              <div>
                <h3 class="text-2xl font-bold mb-4">
                  Rehearsals ({{ schedule.rehearsals?.length || 0 }})
                </h3>

                <div
                  v-if="!schedule.rehearsals || schedule.rehearsals.length === 0"
                  class="text-gray-500 dark:text-gray-300 text-center py-8 bg-gray-50 dark:bg-gray-700 rounded-lg"
                >
                  No rehearsals scheduled yet. Click "Add Rehearsal" to create one!
                </div>

                <div
                  v-else
                  class="space-y-4"
                >
                  <div
                    v-for="rehearsal in sortedRehearsals"
                    :key="rehearsal.id"
                    :class="[
                      'rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow',
                      rehearsal.is_cancelled 
                        ? 'bg-red-50 dark:bg-red-900 border-2 border-red-300 dark:border-red-700' 
                        : 'bg-white dark:bg-gray-700'
                    ]"
                  >
                    <div class="flex justify-between items-start">
                      <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                          <h4 class="text-xl font-semibold">
                            {{ rehearsal.events?.[0]?.title || 'Untitled Rehearsal' }}
                          </h4>
                          <span
                            v-if="rehearsal.is_cancelled"
                            class="px-2 py-1 bg-red-500 text-white text-xs rounded-full"
                          >
                            CANCELLED
                          </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                          <div>
                            <span class="text-gray-600 dark:text-gray-400">
                              <strong>Date:</strong> {{ formatDate(rehearsal.events?.[0]?.date) }}
                            </span>
                          </div>
                          <div>
                            <span class="text-gray-600 dark:text-gray-400">
                              <strong>Time:</strong> {{ formatTime(rehearsal.events?.[0]?.time) }}
                            </span>
                          </div>
                          <div v-if="rehearsal.venue_name">
                            <span class="text-gray-600 dark:text-gray-400">
                              <strong>Venue:</strong> {{ rehearsal.venue_name }}
                            </span>
                          </div>
                          <div v-if="rehearsal.venue_address">
                            <span class="text-gray-600 dark:text-gray-400">
                              <strong>Address:</strong> {{ rehearsal.venue_address }}
                            </span>
                          </div>
                        </div>

                        <div
                          v-if="rehearsal.notes"
                          class="mt-3 text-sm text-gray-600 dark:text-gray-400"
                        >
                          <strong>Notes:</strong> {{ rehearsal.notes }}
                        </div>
                      </div>

                      <div class="flex gap-2 ml-4">
                        <Link
                          :href="route('rehearsals.show', { 
                            band: band.id, 
                            rehearsal_schedule: schedule.id,
                            rehearsal: rehearsal.id 
                          })"
                          class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm"
                        >
                          View
                        </Link>
                        <Link
                          v-if="canWrite"
                          :href="route('rehearsals.edit', { 
                            band: band.id, 
                            rehearsal_schedule: schedule.id,
                            rehearsal: rehearsal.id 
                          })"
                          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm"
                        >
                          Edit
                        </Link>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Container>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { DateTime } from 'luxon';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Container from '@/Components/Container.vue';

const props = defineProps({
    band: {
        type: Object,
        required: true,
    },
    schedule: {
        type: Object,
        required: true,
    },
    canWrite: {
        type: Boolean,
        default: false,
    },
});

const sortedRehearsals = computed(() => {
    if (!props.schedule.rehearsals) return [];
    
    return [...props.schedule.rehearsals].sort((a, b) => {
        const dateA = a.events?.[0]?.date;
        const dateB = b.events?.[0]?.date;
        if (!dateA) return 1;
        if (!dateB) return -1;
        return new Date(dateB) - new Date(dateA);
    });
});

const formatDate = (date) => {
    if (!date) return 'N/A';
    return DateTime.fromISO(date).toLocaleString(DateTime.DATE_FULL);
};

const formatTime = (time) => {
    if (!time) return 'N/A';
    return DateTime.fromFormat(time, 'HH:mm:ss').toLocaleString(DateTime.TIME_SIMPLE);
};

const formatDaysOfWeek = (days) => {
    if (!days || days.length === 0) return 'N/A';
    
    // Capitalize each day and join with commas
    return days.map(day => day.charAt(0).toUpperCase() + day.slice(1)).join(', ');
};

const formatMonthlyPattern = (schedule) => {
    if (!schedule.monthly_pattern) return 'N/A';
    
    if (schedule.monthly_pattern === 'day_of_month') {
        // Format: "On the 15th"
        const day = schedule.day_of_month;
        const suffix = getOrdinalSuffix(day);
        return `On the ${day}${suffix}`;
    } else {
        // Format: "First Monday", "Third Thursday", "Last Friday", etc.
        const pattern = schedule.monthly_pattern.charAt(0).toUpperCase() + schedule.monthly_pattern.slice(1);
        const weekday = schedule.monthly_weekday 
            ? schedule.monthly_weekday.charAt(0).toUpperCase() + schedule.monthly_weekday.slice(1)
            : '';
        return `${pattern} ${weekday}`;
    }
};

const getOrdinalSuffix = (day) => {
    if (!day) return '';
    const j = day % 10;
    const k = day % 100;
    if (j === 1 && k !== 11) return 'st';
    if (j === 2 && k !== 12) return 'nd';
    if (j === 3 && k !== 13) return 'rd';
    return 'th';
};
</script>
