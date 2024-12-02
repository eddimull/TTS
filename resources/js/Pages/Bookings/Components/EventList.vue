<template>
    <div class="p-4 bg-white dark:bg-slate-700 dark:text-gray-50 shadow rounded-lg">
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
<script setup>
import { ref, computed } from "vue";
import EventEditor from "./EventEditor.vue";
import { router } from "@inertiajs/vue3";

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
const isAddingEvent = ref(false);

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString();
};

const formatTime = (timeString) => {
    return new Date(`2000-01-01T${timeString}`).toLocaleTimeString([], {
        timeStyle: "short",
    });
};

const editEvent = (event) => {
    editingEvent.value = JSON.parse(JSON.stringify(event));
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
    // events.value = events.value.filter(e => e.id !== eventId);
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
