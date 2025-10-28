<template>
  <div
    class="p-0 sm:p-2 md:p-4 bg-white dark:bg-slate-700 dark:text-gray-50 shadow rounded-lg"
  >
    <!-- Event List - Hidden when editing -->
    <div v-if="!editingEvent">
      <div
        v-for="event in events"
        :key="event.id"
        class="mb-4 p-4 border dark:border-slate-600 rounded-lg hover:shadow-md transition-shadow bg-white dark:bg-slate-800"
      >
        <div class="flex justify-between items-start">
          <div class="flex-1">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-50 mb-2">
              {{ event.title }}
            </h2>
            <div class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
              <p class="flex items-center gap-2">
                <i class="pi pi-calendar text-blue-500" />
                <span>{{ formatDate(event.date) }}</span>
              </p>
              <p class="flex items-center gap-2">
                <i class="pi pi-clock text-blue-500" />
                <span>{{ formatTime(event.time) }}</span>
              </p>
              <p
                v-if="event.updated_at"
                class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mt-2"
              >
                <i class="pi pi-history" />
                <span>
                  Last updated {{ formatDateTime(event.updated_at) }}
                  <span v-if="event.last_updated_by">
                    by {{ event.last_updated_by.name }}
                  </span>
                </span>
              </p>
            </div>
          </div>
          <div class="flex gap-2 ml-4">
            <button
              class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors flex items-center gap-2"
              @click="viewEvent(event)"
            >
              <i class="pi pi-eye text-sm" />
              View
            </button>
            <button
              class="px-3 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors flex items-center gap-2"
              @click="editEvent(event)"
            >
              <i class="pi pi-pencil text-sm" />
              Edit
            </button>
          </div>
        </div>
      </div>

      <div class="mt-6 flex justify-center">
        <button
          v-if="!isAddingEvent && !viewingEvent"
          class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2 shadow-md hover:shadow-lg font-medium"
          @click="addNewEvent"
        >
          <i class="pi pi-plus text-lg" />
          <span>Add New Event</span>
        </button>
      </div>
    </div>

    <EventDetails
      v-if="viewingEvent && !editingEvent"
      :event="viewingEvent"
      @edit="editEvent"
      @cancel="cancelView"
      @remove-event="removeEvent"
    />

    <EventEditor
      v-if="editingEvent"
      :initial-event="editingEvent"
      @removeEvent="removeEvent"
      @save="saveEvent"
      @cancel="cancelEdit"
    />
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from "vue";
import EventEditor from "./EventEditor.vue";
import EventDetails from "./EventDetails.vue";
import { router, usePage } from "@inertiajs/vue3";
import { DateTime } from "luxon";

const props = defineProps({
    initialEvents: {
        type: Array,
        required: true,
    },
    booking: {
        type: Object,
        required: true,
    },
});

const events = ref(props.initialEvents);
const editingEvent = ref(null);
const viewingEvent = ref(null);
const isAddingEvent = ref(false);

// Check if we should auto-open an event for editing (from query param)
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const editKey = urlParams.get('edit');
    
    if (editKey) {
        const eventToEdit = events.value.find(e => e.key === editKey);
        if (eventToEdit) {
            editEvent(eventToEdit);
            // Clean up URL without reloading
            window.history.replaceState({}, '', window.location.pathname);
        }
    }
});

const formatDate = (dateString) => {
    return DateTime.fromISO(dateString).toLocaleString(DateTime.DATE_HUGE);
};

const formatTime = (timeString) => {
    return new Date(`2000-01-01T${timeString}`).toLocaleTimeString([], {
        timeStyle: "short",
    });
};

const formatDateTime = (dateTimeString) => {
    if (!dateTimeString) return '';
    
    // Try different parsing methods
    let dt = DateTime.fromISO(dateTimeString);
    
    if (!dt.isValid) {
        dt = DateTime.fromSQL(dateTimeString);
    }
    
    if (!dt.isValid) {
        dt = DateTime.fromRFC2822(dateTimeString);
    }
    
    return dt.isValid ? dt.toRelative() : '';
};

const viewEvent = (event) => {
    viewingEvent.value = JSON.parse(JSON.stringify(event));
    editingEvent.value = null;
};

const editEvent = (event) => {
    editingEvent.value = JSON.parse(JSON.stringify(event));
    viewingEvent.value = null;
};

const cancelView = () => {
    viewingEvent.value = null;
};

const saveEvent = (updatedEvent) => {
    if (updatedEvent.id) {
        // Updating an existing event
        router.put(
            route("Update Booking Event", [
                props.booking.band_id,
                props.booking.id,
                updatedEvent.id,
            ]),
            updatedEvent,
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    } else {
        // Creating a new event
        router.post(
            route("Update Booking Event", [
                props.booking.band_id,
                props.booking.id,
            ]),
            updatedEvent,
            {
                preserveState: false,
                preserveScroll: true,
            }
        );
    }
};

const cancelEdit = () => {
    editingEvent.value = null;
    isAddingEvent.value = false;
};

const addNewEvent = () => {
    isAddingEvent.value = true;
    editingEvent.value = {
        title: props.booking.name + " Event",
        date: props.booking.date,
        event_type_id: props.booking.event_type_id,
        additional_data: {
            times: [
                {
                    title: "End",
                    time: `${props.booking.date}T${props.booking.end_time}`,
                },
                {
                    title: "Band Load-In",
                    time: `${props.booking.date}T${props.booking.start_time}`,
                },
            ],
            public: false,
            lodging: [
                { title: "Lodging Provided", data: false, type: "checkbox" },
                { title: "Lodging Notes", data: "", type: "text" },
            ],
            outside: false,
            backline_provided: false,
            production_needed: false,
            wedding: {
                onsite: false,
                dances: [
                    { title: "First Dance", data: "TBD" },
                    { title: "Father/Daughter Dance", data: "TBD" },
                    { title: "Mother/Son Dance", data: "TBD" },
                    { title: "Bouquet/Garter", data: "TBD" },
                    { title: "Money", data: "TBD" },
                ],
            },
        },
        time: props.booking.start_time,
    };
};

const removeEvent = (eventId) => {
    router.delete(
        route("Delete Booking Event", [
            props.booking.band_id,
            props.booking.id,
            eventId,
        ]),
        {
            preserveState: false,
            preserveScroll: false,
        }
    );
};
</script>
