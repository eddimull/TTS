<template>
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
    <h5 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 border-b border-gray-200 dark:border-gray-600 pb-2">
      Upcoming Events
    </h5>
    <ul class="space-y-3">
      <li
        v-for="event in nextFourEvents"
        :key="event.id"
        class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors duration-200 cursor-pointer"
        @click="$inertia.visit(route('Booking Events', {
          band: event.band_id,
          booking: event.booking_id,
        }))"
      >
        <div class="font-semibold text-gray-900 dark:text-gray-100 mb-1">
          {{ event.title }}
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-300 flex flex-col sm:flex-row sm:items-center sm:gap-2 lg:flex-col lg:items-start">
          <span class="font-medium">{{ formatDate(event) }}</span>
          <span class="hidden sm:inline lg:hidden text-gray-400">•</span>
          <span>{{ event.venue_name }}</span>
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
          {{ formatTime(event) }}
        </div>
      </li>
      <li
        v-if="nextFourEvents.length === 0"
        class="bg-gray-50 dark:bg-slate-700 rounded-lg p-6 text-center border border-gray-200 dark:border-gray-600"
      >
        <div class="text-gray-500 dark:text-gray-400">
          <svg
            class="w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-500"
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
          No upcoming events
        </div>
      </li>
    </ul>
  </div>
</template>

<script>
import { DateTime } from "luxon";

export default {
  name: "Upcoming",
  props: {
    events: {
      type: Array,
      required: true,
    },
  },
  computed: {
    nextFourEvents() {
      const now = DateTime.now();
      return this.events
        .filter((event) => {
          const eventDate = DateTime.fromISO(event.date);
          return eventDate > now;
        })
        .sort((a, b) => {
          const dateA = DateTime.fromISO(a.date);
          const dateB = DateTime.fromISO(b.date);
          return dateA - dateB;
        })
        .slice(0, 4);
    },
  },
  methods: {
    formatDate(event) {
      return DateTime.fromISO(event.date).toFormat("MMM dd, yyyy");
    },
    formatTime(event) {
      return DateTime.fromISO(event.date + "T" + event.time).toFormat("h:mm a");
    },
  },
};
</script>