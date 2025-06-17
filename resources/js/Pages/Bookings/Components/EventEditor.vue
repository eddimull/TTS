<template>
    <div
        class="mt-4 p-4 bg-gray-100 dark:bg-slate-700 dark:text-gray-50 rounded-lg"
    >
        <h2 class="text-2xl font-bold mb-4">Edit Event: {{ event.title }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block mb-2">Title</label>
                <input
                    v-model="event.title"
                    type="text"
                    class="w-full p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
                />
            </div>
            <div>
                <label class="block mb-2">Date</label>
                <input
                    v-model="event.date"
                    type="date"
                    class="w-full p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
                />
            </div>
            <div>
                <label class="block mb-2">Time (show time)</label>
                <input
                    v-model="event.time"
                    type="time"
                    class="w-full p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
                />
            </div>
        </div>
        <div class="mt-4">
            <label class="block mb-2">Notes</label>
            <Editor
                v-model="event.notes"
                class="w-full p-2 border rounded"
                editor-style="height: 320px"
            />
        </div>

        <!-- Times Section -->
        <div class="mt-4 p-4">
            <h3 class="text-xl font-semibold mb-4">Timeline</h3>
            <TransitionGroup name="time-entries" tag="div" class="space-y-4">
                <div
                    v-for="(entry, index) in sortedTimeEntries"
                    :key="entry.id || index"
                    class="flex flex-col sm:flex-row items-start sm:items-center mb-4 space-y-2 sm:space-y-0 sm:space-x-2 transition-all duration-300"
                >
                    <input
                        v-model.trim="entry.title"
                        type="text"
                        placeholder="Time title"
                        class="w-full sm:w-1/3 p-2 border rounded dark:bg-slate-700 dark:text-gray-50"
                        :disabled="entry.isEventTime"
                        :class="{'bg-gray-100 dark:bg-slate-600': entry.isEventTime}"
                    />
                    <input
                        v-model="entry.time"
                        type="datetime-local"
                        class="w-full sm:w-1/3 p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
                        :disabled="entry.isEventTime"
                        :class="{'bg-gray-100 dark:bg-slate-600': entry.isEventTime}"
                    />
                    <button
                        v-if="!entry.isEventTime"
                        class="w-full sm:w-auto px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                        @click="removeTimeEntry(entry.id)"
                    >
                        Remove
                    </button>
                    <span 
                        v-else 
                        class="w-full sm:w-auto px-4 py-2 text-gray-500 italic"
                    >
                        Event time (fixed)
                    </span>
                </div>
            </TransitionGroup>
            <div class="mt-4">
                <button
                    class="w-full sm:w-auto px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors"
                    @click="addTimeEntry"
                >
                    Add Time Entry
                </button>
            </div>
        </div>
        <div class="mt-4">
            <div>
                <h4 class="text-xl font-semibold mb-2">Attire</h4>
                <Editor
                    v-model="event.additional_data.attire"
                    class="w-full p-2 border rounded"
                    editor-style="height: 320px"
                />
            </div>
        </div>
        <div class="mt-4">
            <h3 class="text-xl font-semibold mb-2">Additional Data</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <template
                    v-for="(value, key) in event.additional_data"
                    :key="key"
                >
                    <template v-if="!exclusions.includes(key)">
                        <div v-if="typeof value === 'object' && value !== null">
                            <h4 class="font-semibold mb-2">
                                {{ formatLabel(key) }}
                            </h4>
                            <div
                                v-for="(subValue, subKey) in value"
                                :key="subKey"
                                class="mb-2"
                            >
                                <label class="block mb-1">{{
                                    formatLabel(subKey)
                                }}</label>
                                <input
                                    v-if="
                                        getInputType(subKey, subValue) !==
                                        'checkbox'
                                    "
                                    v-model="event.additional_data[key][subKey]"
                                    :type="getInputType(subKey, subValue)"
                                    :readonly="
                                        getInputType(subKey, subValue) ===
                                        'readonly'
                                    "
                                    class="w-full p-2 border rounded"
                                    :class="{
                                        'bg-gray-100':
                                            getInputType(subKey, subValue) ===
                                            'readonly',
                                    }"
                                />
                                <input
                                    v-else
                                    v-model="event.additional_data[key][subKey]"
                                    type="checkbox"
                                    class="form-checkbox h-5 w-5 text-blue-600"
                                />
                            </div>
                        </div>
                        <div v-else>
                            <label class="block mb-2">{{
                                formatLabel(key)
                            }}</label>
                            <input
                                v-if="getInputType(key, value) !== 'checkbox'"
                                v-model="event.additional_data[key]"
                                :type="getInputType(key, value)"
                                :readonly="
                                    getInputType(key, value) === 'readonly'
                                "
                                class="w-full p-2 border rounded"
                                :class="{
                                    'bg-gray-100':
                                        getInputType(key, value) === 'readonly',
                                }"
                            />
                            <input
                                v-else
                                v-model="event.additional_data[key]"
                                type="checkbox"
                                class="form-checkbox h-5 w-5 text-blue-600"
                            />
                        </div>
                    </template>
                </template>
            </div>
        </div>
        <!-- Lodging info-->
        <div class="mt-4">
            <h3 class="text-xl font-semibold mb-2">Lodging Information</h3>
            <div class="grid grid-cols-1 gap-4">
                <div
                    v-for="(value, key) in event.additional_data.lodging"
                    :key="key"
                >
                    <label class="block mb-1">{{ value.title }}</label>
                    <input
                        v-model="event.additional_data.lodging[key].data"
                        :type="event.additional_data.lodging[key].type"
                        :class="{
                            'form-checkbox h-5 w-5 text-blue-600':
                                event.additional_data.lodging[key].type ===
                                'checkbox',
                            'w-full p-2 border rounded dark:bg-slate-700 dark:text-gray-50':
                                event.additional_data.lodging[key].type ===
                                'text',
                        }"
                    />
                </div>
            </div>
        </div>
        <!-- Wedding-specific fields -->
        <div v-if="isWedding" class="mt-4">
            <h3 class="text-xl font-semibold mb-2">Wedding Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="font-semibold mb-2">Dances</h4>
                    <div
                        v-for="dance in event.additional_data.wedding.dances"
                        :key="dance.title"
                        class="mb-2"
                    >
                        <label class="block mb-1">{{ dance.title }}</label>
                        <input
                            v-model="dance.data"
                            type="text"
                            class="w-full p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
                        />
                    </div>
                </div>
                <div>
                    <label class="block mb-2">Onsite</label>
                    <input
                        v-model="event.additional_data.wedding.onsite"
                        type="checkbox"
                        class="form-checkbox h-5 w-5 text-blue-600"
                    />
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-around lg:justify-between space-x-2">
            <div class="block">
                <button
                    class="p-1 m-0 lg:px-4 bg-red-500 text-white rounded hover:bg-red-600"
                    @click="removeEvent"
                >
                    Remove Event
                </button>
            </div>
            <div class="block justify-end">
                <button
                    class="p-1 px-4 mr-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400"
                    @click="cancel"
                >
                    Cancel
                </button>
                <button
                    class="p-1 px-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                    @click="save"
                >
                    Save
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";

const props = defineProps({
    initialEvent: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["save", "cancel", "removeEvent"]);

const event = ref(JSON.parse(JSON.stringify(props.initialEvent)));

const isWedding = computed(() => event.value.event_type_id === 1);

const formatLabel = (key) => {
    return key
        .split("_")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
};

const getInputType = (key, value) => {
    const booleanFields = [
        "public",
        "outside",
        "onsite",
        "backline_provided",
        "production_needed",
    ];
    if (booleanFields.includes(key)) return "checkbox";
    if (key === "migrated_from_event_id") return "readonly";
    if (typeof value === "number") return "number";
    return "text";
};

const exclusions = ["times", "attire", "lodging", "wedding", "onsite"];

const timeEntries = ref(
    (event.value.additional_data.times || []).map((entry) => ({
        ...entry,
        id: crypto.randomUUID(), // Add unique ID to each entry
    }))
);

const sortedTimeEntries = computed(() => {
    return [
        {
            id: 'event-time', // Special ID to identify this entry
            title: 'Show Time',
            time: `${event.value.date}T${event.value.time}`,
            isEventTime: true // Flag to identify this as a special non-removable entry
        },
        ...timeEntries.value
    ].sort((a, b) => {
        const timeA = new Date(a.time || 0);
        const timeB = new Date(b.time || 0);
        return timeA - timeB;
    });
});

const addTimeEntry = () => {
    const defaultDateTime = `${event.value.date}T${event.value.time}`;
    timeEntries.value.push({
        id: crypto.randomUUID(),
        title: "New Time Entry",
        time: defaultDateTime,
    });
};

const removeTimeEntry = (id) => {
    // Don't allow removal of the event time entry
    if (id === 'event-time') return;
    
    const index = timeEntries.value.findIndex((entry) => entry.id === id);
    confirm(
        `Are you sure you want to remove this time entry - ${timeEntries.value[index].title}?`
    ) && timeEntries.value.splice(index, 1);
};

const save = () => {
    // Update the times in the event object
    event.value.additional_data.times = timeEntries.value.filter(
        (entry) => entry.title && entry.time
    );
    emit("save", event.value);
};

const cancel = () => {
    emit("cancel");
};

const removeEvent = () => {
    emit("removeEvent", event.value.id);
};
</script>
<style scoped>
.time-entries-move, /* apply transition to moving elements */
.time-entries-enter-active,
.time-entries-leave-active {
    transition: all 0.5s ease;
}

.time-entries-enter-from,
.time-entries-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly */
.time-entries-leave-active {
    position: absolute;
}
</style>
