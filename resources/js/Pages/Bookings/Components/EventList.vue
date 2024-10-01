<!-- EventList.vue -->
<script setup>
import { ref, computed } from 'vue';
import EventEditor from './EventEditor.vue';
import { Inertia } from '@inertiajs/inertia';

const props = defineProps({
  initialEvents: {
    type: Array,
    required: true,
  },
  booking: {
    type: Object,
    required: true
  }
});

const events = ref(props.initialEvents);
const editingEvent = ref(null);
const isAddingEvent = ref(false);

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString();
};

const formatTime = (timeString) => {
  return new Date(`2000-01-01T${timeString}`).toLocaleTimeString([], { timeStyle: 'short' });
};

const editEvent = (event) => {
  editingEvent.value = JSON.parse(JSON.stringify(event));
};

const saveEvent = (updatedEvent) => {
  if (updatedEvent.id) {
    // Updating an existing event
    Inertia.put(route('Update Booking Event', [props.booking.band_id, props.booking.id, updatedEvent.id]), updatedEvent, {
      preserveState: false,
      preserveScroll: true,
    }).then(() => {
      const index = events.value.findIndex(e => e.id === updatedEvent.id);
      if (index !== -1) {
        events.value[index] = updatedEvent;
      }
      editingEvent.value = null;
    });
  } else {
    // Creating a new event
    Inertia.post(route('Update Booking Event', [props.booking.band_id, props.booking.id]), updatedEvent, {
      preserveState: false,
      preserveScroll: true,
    }).then((response) => {
      // Assuming the server returns the newly created event with an ID
      const newEvent = response.props.event;
      events.value.push(newEvent);
      editingEvent.value = null;
      isAddingEvent.value = false;
    });
  }
};

const cancelEdit = () => {
  editingEvent.value = null;
  isAddingEvent.value = false;
};

const addNewEvent = () => {
  isAddingEvent.value = true;
  editingEvent.value = {
    title: props.booking.name + ' Event',
    date: props.booking.date,
    additional_data: {
      times: {
        end_time: `${props.booking.date}T${props.booking.end_time}`,
        band_loadin_time: `${props.booking.date}T${props.booking.start_time}`,
      },
      'public': false,
      'lodging': false,
      'outside': false,
      'backline_provided': false,
      'production_needed': false,
      'onsite': false
    },
    time: props.booking.start_time,
  };
};

const removeEvent = (eventId) => {
  events.value = events.value.filter(e => e.id !== eventId);
  Inertia.delete(route('Delete Booking Event', [props.booking.band_id, props.booking.id, eventId]));
};
</script>

<template>
  <div class="p-4 bg-white shadow rounded-lg">
    <div
      v-for="event in events"
      :key="event.id"
      class="mb-4 p-4 border rounded"
    >
      <h2 class="text-xl font-semibold">
        {{ event.title }}
      </h2>
      <p>Date: {{ formatDate(event.date) }}</p>
      <p>Time: {{ formatTime(event.time) }}</p>
      <div class="mt-2">
        <button
          class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 mr-2"
          @click="editEvent(event)"
        >
          Edit
        </button>
        <button
          class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600"
          @click="removeEvent(event.id)"
        >
          Remove
        </button>
      </div>
    </div>

    <button
      v-if="!isAddingEvent && !editingEvent"
      class="mt-4 px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
      @click="addNewEvent"
    >
      Add New Event
    </button>

    <EventEditor
      v-if="editingEvent"
      :initial-event="editingEvent"
      @save="saveEvent"
      @cancel="cancelEdit"
    />
  </div>
</template>
