<template>
  <div>
    <h3 class="text-xl font-semibold mb-4">
      Timeline
    </h3>
    <TransitionGroup
      name="time-entries"
      tag="div"
      class="space-y-4"
    >
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
        >
        <input
          v-model="entry.time"
          type="datetime-local"
          class="w-full sm:w-1/3 p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
          :disabled="entry.isEventTime"
          :class="{'bg-gray-100 dark:bg-slate-600': entry.isEventTime}"
        >
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
</template>

<script setup>
import { ref, computed } from "vue";

const props = defineProps({
    eventDate: {
        type: String,
        required: true,
    },
    eventTime: {
        type: String,
        required: true,
    },
    times: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update:times"]);

const timeEntries = ref(
    props.times.map((entry) => ({
        ...entry,
        id: entry.id || crypto.randomUUID(),
    }))
);

const sortedTimeEntries = computed(() => {
    emitUpdate();
    return [
        {
            id: 'event-time',
            title: 'Show Time',
            time: `${props.eventDate}T${props.eventTime}`,
            isEventTime: true
        },
        ...timeEntries.value
    ].sort((a, b) => {
        const timeA = new Date(a.time || 0);
        const timeB = new Date(b.time || 0);
        return timeA - timeB;
    });
});

const addTimeEntry = () => {
    const defaultDateTime = `${props.eventDate}T${props.eventTime}`;
    const newEntry = {
        id: crypto.randomUUID(),
        title: "New Time Entry",
        time: defaultDateTime,
    };
    timeEntries.value.push(newEntry);
    emitUpdate();
};

const removeTimeEntry = (id) => {
    if (id === 'event-time') return;
    
    const index = timeEntries.value.findIndex((entry) => entry.id === id);
    if (index !== -1) {
        const confirmed = confirm(
            `Are you sure you want to remove this time entry - ${timeEntries.value[index].title}?`
        );
        if (confirmed) {
            timeEntries.value.splice(index, 1);
            emitUpdate();
        }
    }
};

const emitUpdate = () => {
    const filteredEntries = timeEntries.value.filter(
        (entry) => entry.title && entry.time
    );
    emit("update:times", filteredEntries);
};
</script>

<style scoped>
.time-entries-move,
.time-entries-enter-active,
.time-entries-leave-active {
    transition: all 0.5s ease;
}

.time-entries-enter-from,
.time-entries-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}

.time-entries-leave-active {
    position: absolute;
}
</style>