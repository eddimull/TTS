// EventList.vue
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
    console.log('you doin stuff?');
  const index = events.value.findIndex(e => e.id === updatedEvent.id);
  if (index !== -1) {
    events.value[index] = updatedEvent;
  }
  editingEvent.value = null;
  Inertia.post(route('Update Booking Event', [props.booking.band_id,props.booking.id,updatedEvent.id]),updatedEvent);
};

const cancelEdit = () => {
  editingEvent.value = null;
};
</script>

<template>
  <div class="p-4 bg-white shadow rounded-lg">
    <div v-for="event in events" :key="event.id" class="mb-4 p-4 border rounded">
      <h2 class="text-xl font-semibold">{{ event.title }}</h2>
      <p>Date: {{ formatDate(event.date) }}</p>
      <p>Time: {{ formatTime(event.time) }}</p>
      <button 
        @click="editEvent(event)" 
        class="mt-2 px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
      >
        Edit
      </button>
    </div>
    
    <EventEditor 
      v-if="editingEvent" 
      :initialEvent="editingEvent" 
      @save="saveEvent"
      @cancel="cancelEdit"
    />
  </div>
</template>