<template>
  <div class="space-y-6">
    <div v-if="events.length === 0" class="text-center py-16 text-gray-500 dark:text-gray-400">
      <i class="pi pi-users text-4xl mb-4 block" />
      No events yet for this booking.
    </div>

    <div v-for="event in events" :key="event.id" class="bg-white dark:bg-slate-800 rounded-xl shadow-md p-6">
      <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200 dark:border-gray-700">
        <div>
          <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ event.title }}</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ event.date }}<span v-if="event.time"> &middot; {{ event.time }}</span></p>
        </div>
      </div>

      <RosterSection
        :model-value="event"
        :band-id="band.id"
        @update:model-value="val => updateEvent(event.id, val)"
      />
    </div>
  </div>
</template>

<script setup>
import BookingLayout from './Layout/BookingLayout.vue';
import RosterSection from './Components/EventEditor/RosterSection.vue';

defineOptions({ layout: BookingLayout });

const props = defineProps({
  booking: { type: Object, required: true },
  band: { type: Object, required: true },
  events: { type: Array, default: () => [] },
});

const updateEvent = (eventId, updatedEvent) => {
  // roster_id changes are saved directly in RosterSection via its own API calls;
  // nothing to bubble up here unless the parent page needs to reflect changes.
};
</script>
