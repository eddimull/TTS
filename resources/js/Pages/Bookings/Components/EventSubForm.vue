<template>
  <div class="border border-gray-200 dark:border-gray-700 rounded-md p-4 space-y-3 bg-white dark:bg-slate-800">
    <div class="flex justify-between items-start">
      <h4 class="font-medium text-gray-900 dark:text-gray-50">
        {{ localEvent.title || 'Untitled event' }}
      </h4>
      <Button
        type="button"
        icon="pi pi-trash"
        severity="danger"
        text
        :disabled="!canDelete"
        :title="canDelete ? 'Remove this event' : 'A booking must have at least one event'"
        @click="$emit('delete')"
      />
    </div>

    <p
      v-if="saveError"
      class="text-sm text-red-600 dark:text-red-400"
    >
      {{ saveError }}
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <TextInput
        v-model="localEvent.title"
        name="title"
        label="Title"
      />
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-50">Date</label>
        <input
          v-model="localEvent.date"
          type="date"
          class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-50">Start time</label>
        <input
          v-model="localEvent.start_time"
          type="time"
          class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-50">End time</label>
        <input
          v-model="localEvent.end_time"
          type="time"
          class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
        >
      </div>
      <LocationAutocomplete
        v-model="localEvent.venue_name"
        name="venue_name"
        label="Venue"
        placeholder="Enter a venue name or address"
        @location-selected="handleLocationSelected"
      />
      <TextInput
        v-model="localEvent.venue_address"
        name="venue_address"
        label="Venue address"
      />
      <div v-if="showPrice">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-50">Price</label>
        <input
          v-model="localEvent.price"
          type="number"
          step="0.01"
          min="0"
          class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
        >
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import Button from 'primevue/button';
import LocationAutocomplete from '@/Components/LocationAutocomplete.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    showPrice: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: true,
    },
    saveError: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'delete']);

const localEvent = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});

function handleLocationSelected(locationData) {
    localEvent.value.venue_address = locationData.result.formatted_address;
}
</script>
