
<template>
  <div class="mt-4 p-4 bg-gray-100 rounded-lg">
    <h2 class="text-2xl font-bold mb-4">
      Edit Event: {{ event.title }}
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block mb-2">Title</label>
        <input
          v-model="event.title"
          type="text"
          class="w-full p-2 border rounded"
        >
      </div>
      <div>
        <label class="block mb-2">Date</label>
        <input
          v-model="event.date"
          type="date"
          class="w-full p-2 border rounded"
        >
      </div>
      <div>
        <label class="block mb-2">Time</label>
        <input
          v-model="event.time"
          type="time"
          class="w-full p-2 border rounded"
        >
      </div>
    </div>
    <div class="mt-4">
      <label class="block mb-2">Notes</label>
      <textarea
        v-model="event.notes"
        class="w-full p-2 border rounded"
        rows="3"
      />
    </div>

    <!-- Times Section -->
    <div class="mt-4">
      <h3 class="text-xl font-semibold mb-2">
        Times
      </h3>
      <div
        v-for="(entry, index) in timeEntries"
        :key="index"
        class="flex items-center mb-2"
      >
        <input
          v-model.trim="entry.title"
          type="text"
          placeholder="Time title"
          class="w-1/4 p-2 border rounded mr-2"
        >
        <select
          v-model="entry.isLoadIn"
          class="w-1/4 p-2 border rounded mr-2"
        >
          <option :value="false">
            Regular Time
          </option>
          <option :value="true">
            Load-in Time
          </option>
        </select>
        <input
          v-model="entry.value"
          type="datetime-local"
          class="w-1/3 p-2 border rounded mr-2"
        >
        <button
          class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600"
          @click="removeTimeEntry(index)"
        >
          Remove
        </button>
      </div>
      <div class="mt-2">
        <button
          class="mr-2 px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600"
          @click="addTimeEntry(false)"
        >
          Add Regular Time
        </button>
        <button
          class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
          @click="addTimeEntry(true)"
        >
          Add Load-in Time
        </button>
      </div>
    </div>

    <div class="mt-4">
      <h3 class="text-xl font-semibold mb-2">
        Additional Data
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <template
          v-for="(value, key) in event.additional_data"
          :key="key"
        >
          <template v-if="key !== 'dances' && key !== 'onsite' && key !== 'times'">
            <div v-if="typeof value === 'object' && value !== null">
              <h4 class="font-semibold mb-2">
                {{ formatLabel(key) }}
              </h4>
              <div
                v-for="(subValue, subKey) in value"
                :key="subKey"
                class="mb-2"
              >
                <label class="block mb-1">{{ formatLabel(subKey) }}</label>
                <input
                  v-if="getInputType(subKey, subValue) !== 'checkbox'"
                  v-model="event.additional_data[key][subKey]"
                  :type="getInputType(subKey, subValue)"
                  :readonly="getInputType(subKey, subValue) === 'readonly'"
                  class="w-full p-2 border rounded"
                  :class="{ 'bg-gray-100': getInputType(subKey, subValue) === 'readonly' }"
                >
                <input
                  v-else
                  v-model="event.additional_data[key][subKey]"
                  type="checkbox"
                  class="form-checkbox h-5 w-5 text-blue-600"
                >
              </div>
            </div>
            <div v-else>
              <label class="block mb-2">{{ formatLabel(key) }}</label>
              <input
                v-if="getInputType(key, value) !== 'checkbox'"
                v-model="event.additional_data[key]"
                :type="getInputType(key, value)"
                :readonly="getInputType(key, value) === 'readonly'"
                class="w-full p-2 border rounded"
                :class="{ 'bg-gray-100': getInputType(key, value) === 'readonly' }"
              >
              <input
                v-else
                v-model="event.additional_data[key]"
                type="checkbox"
                class="form-checkbox h-5 w-5 text-blue-600"
              >
            </div>
          </template>
        </template>
      </div>
    </div>

    <!-- Wedding-specific fields -->
    <div
      v-if="isWedding"
      class="mt-4"
    >
      <h3 class="text-xl font-semibold mb-2">
        Wedding Details
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <h4 class="font-semibold mb-2">
            Dances
          </h4>
          <div
            v-for="(value, key) in event.additional_data.dances"
            :key="key"
            class="mb-2"
          >
            <label class="block mb-1">{{ formatLabel(key) }}</label>
            <input
              v-model="event.additional_data.dances[key]"
              type="text"
              class="w-full p-2 border rounded"
            >
          </div>
        </div>
        <div>
          <label class="block mb-2">Onsite</label>
          <input
            v-model="event.additional_data.onsite"
            type="checkbox"
            class="form-checkbox h-5 w-5 text-blue-600"
          >
        </div>
      </div>
    </div>

    <div class="mt-4 flex justify-end space-x-2">
      <button
        class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400"
        @click="cancel"
      >
        Cancel
      </button>
      <button
        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
        @click="save"
      >
        Save Changes
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  initialEvent: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['save', 'cancel']);

const event = ref(JSON.parse(JSON.stringify(props.initialEvent)));

const isWedding = computed(() => event.value.event_type_id === 1);

const formatLabel = (key) => {
  return key.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const getInputType = (key, value) => {
  const booleanFields = ['public', 'lodging', 'outside', 'backline_provided', 'production_needed', 'onsite'];
  if (booleanFields.includes(key)) return 'checkbox';
  if (key === 'migrated_from_event_id') return 'readonly';
  if (typeof value === 'number') return 'number';
  return 'text';
};

const timeEntries = ref(Object.entries(event.value.additional_data.times || {}).map(([key, value]) => {
  const isLoadIn = key.includes('loadin');
  const title = key.replace('_loadin_time', '').replace('_time', '');
  return {
    title,
    isLoadIn,
    value,
    get key() { return `${this.title}${this.isLoadIn ? '_loadin' : ''}_time`; }
  };
}));

const addTimeEntry = (isLoadIn = false) => {
  const defaultDateTime = `${event.value.date}T${event.value.time}`;
  timeEntries.value.push({
    title: isLoadIn ? 'New Load-in Time' : 'New Time',
    isLoadIn,
    value: defaultDateTime
  });
};

const removeTimeEntry = (index) => {
  timeEntries.value.splice(index, 1);
};

const save = () => {
  // Update the times in the event object
  event.value.additional_data.times = Object.fromEntries(
    timeEntries.value
      .filter(entry => entry.title && entry.value)
      .map(entry => [entry.key, entry.value])
  );
  emit('save', event.value);
};

const cancel = () => {
  emit('cancel');
};
</script>
