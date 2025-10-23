<template>
  <BreezeAuthenticatedLayout>
    <Container class="dark:bg-slate-600 md:container md:mx-auto">
      <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
          <div class="componentPanel overflow-auto shadow-sm sm:rounded-lg">
            <div class="p-6">
              <!-- Header -->
              <div class="flex justify-between items-start mb-6">
                <div>
                  <div class="flex items-center gap-3 mb-2">
                    <h2 class="text-3xl font-bold">
                      {{ rehearsal.events?.[0]?.title || 'Untitled Rehearsal' }}
                    </h2>
                    <span
                      v-if="rehearsal.is_cancelled"
                      class="px-3 py-1 bg-red-500 text-white text-sm rounded-full"
                    >
                      Cancelled
                    </span>
                  </div>
                  <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                    Schedule: <Link
                      :href="route('rehearsal-schedules.show', { band: band.id, rehearsal_schedule: schedule.id })"
                      class="text-blue-500 hover:text-blue-700"
                    >
                      {{ schedule.name }}
                    </Link>
                  </div>
                  <Link
                    :href="route('rehearsal-schedules.show', { band: band.id, rehearsal_schedule: schedule.id })"
                    class="text-blue-500 hover:text-blue-700 text-sm"
                  >
                    ← Back to Schedule
                  </Link>
                </div>
                <div class="flex gap-2">
                  <Link
                    v-if="canWrite"
                    :href="route('rehearsals.toggle-cancelled', { 
                      band: band.id, 
                      rehearsal_schedule: schedule.id,
                      rehearsal: rehearsal.id 
                    })"
                    method="post"
                    as="button"
                    :class="rehearsal.is_cancelled 
                      ? 'bg-green-500 hover:bg-green-700' 
                      : 'bg-orange-500 hover:bg-orange-700'"
                    class="text-white font-bold py-2 px-4 rounded"
                  >
                    {{ rehearsal.is_cancelled ? 'Reactivate' : 'Cancel' }} Rehearsal
                  </Link>
                  <Link
                    v-if="canWrite"
                    :href="route('rehearsals.edit', { 
                      band: band.id, 
                      rehearsal_schedule: schedule.id,
                      rehearsal: rehearsal.id 
                    })"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                  >
                    Edit Rehearsal
                  </Link>
                </div>
              </div>

              <!-- Event Information Card -->
              <div class="mb-6 p-6 bg-blue-50 dark:bg-blue-900 rounded-lg">
                <h3 class="text-xl font-semibold mb-4 flex items-center">
                  <svg
                    class="w-5 h-5 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>
                  Event Details
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Date</span>
                    <p class="text-gray-900 dark:text-white text-lg">
                      {{ formatDate(rehearsal.events?.[0]?.date) }}
                    </p>
                  </div>

                  <div>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Time</span>
                    <p class="text-gray-900 dark:text-white text-lg">
                      {{ formatTime(rehearsal.events?.[0]?.time) }}
                    </p>
                  </div>

                  <div v-if="rehearsal.events?.[0]?.event_type">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Event Type</span>
                    <p class="text-gray-900 dark:text-white">
                      {{ rehearsal.events[0].event_type.name }}
                    </p>
                  </div>

                  <div v-if="rehearsal.events?.[0]?.notes">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Event Notes</span>
                    <p class="text-gray-900 dark:text-white whitespace-pre-wrap">
                      {{ rehearsal.events[0].notes }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Venue Information Card -->
              <div class="mb-6 p-6 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <h3 class="text-xl font-semibold mb-4 flex items-center">
                  <svg
                    class="w-5 h-5 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                    />
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                  </svg>
                  Venue Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Venue Name</span>
                    <p class="text-gray-900 dark:text-white">
                      {{ rehearsal.venue_name || schedule.location_name || 'Not specified' }}
                    </p>
                  </div>

                  <div>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Address</span>
                    <p class="text-gray-900 dark:text-white whitespace-pre-wrap">
                      {{ rehearsal.venue_address || schedule.location_address || 'Not specified' }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Rehearsal Notes Card -->
              <div
                v-if="rehearsal.notes"
                class="mb-6 p-6 bg-yellow-50 dark:bg-yellow-900 rounded-lg"
              >
                <h3 class="text-xl font-semibold mb-4 flex items-center">
                  <svg
                    class="w-5 h-5 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                  </svg>
                  Rehearsal Notes
                </h3>
                <p class="text-gray-900 dark:text-white whitespace-pre-wrap">
                  {{ rehearsal.notes }}
                </p>
              </div>

              <!-- Associated Bookings Card -->
              <div
                v-if="rehearsal.associations && rehearsal.associations.length > 0"
                class="mb-6 p-6 bg-green-50 dark:bg-green-900 rounded-lg"
              >
                <h3 class="text-xl font-semibold mb-4 flex items-center">
                  <svg
                    class="w-5 h-5 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                    />
                  </svg>
                  Associated Bookings
                </h3>
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                  This rehearsal is preparing for the following events:
                </div>

                <div class="space-y-3">
                  <div
                    v-for="association in rehearsal.associations"
                    :key="association.id"
                    class="p-4 bg-white dark:bg-gray-800 rounded-lg"
                  >
                    <div
                      v-if="association.associable"
                      class="flex justify-between items-start"
                    >
                      <div class="flex-1">
                        <div class="font-semibold text-gray-900 dark:text-white">
                          {{ association.associable.name }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                          {{ formatDate(association.associable.date) }} - {{ association.associable.venue_name }}
                        </div>
                        <div
                          v-if="association.notes"
                          class="text-sm text-gray-500 dark:text-gray-500 mt-1"
                        >
                          {{ association.notes }}
                        </div>
                      </div>
                      <Link
                        :href="route('Show Booking', { booking: association.associable.id })"
                        class="text-blue-500 hover:text-blue-700 text-sm ml-4"
                      >
                        View Booking →
                      </Link>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Calendar Link -->
              <div
                v-if="rehearsal.events?.[0]?.google_events?.[0]?.google_calendar_id"
                class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg"
              >
                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                  <svg
                    class="w-5 h-5 mr-2"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                      clip-rule="evenodd"
                    />
                  </svg>
                  This rehearsal is synced to Google Calendar
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
import { Link } from '@inertiajs/vue3';
import { DateTime } from 'luxon';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Container from '@/Components/Container.vue';

defineProps({
    band: {
        type: Object,
        required: true,
    },
    schedule: {
        type: Object,
        required: true,
    },
    rehearsal: {
        type: Object,
        required: true,
    },
    canWrite: {
        type: Boolean,
        default: false,
    },
});

const formatDate = (date) => {
    if (!date) return 'N/A';
    return DateTime.fromISO(date).toLocaleString(DateTime.DATE_FULL);
};

const formatTime = (time) => {
    if (!time) return 'N/A';
    return DateTime.fromFormat(time, 'HH:mm:ss').toLocaleString(DateTime.TIME_SIMPLE);
};
</script>
