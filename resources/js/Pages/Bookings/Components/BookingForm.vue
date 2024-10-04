<template>
  <Container class="p-4">
    <form
      class="space-y-6"
      @submit.prevent="updateBooking"
    >
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <TextInput
          v-model="form.name"
          name="name"
          label="Name"
        />
        <div>
          <label
            for="event_type_id"
            class="block text-sm font-medium text-gray-700"
          >Event Type</label>
          <select
            id="event_type_id"
            v-model="form.event_type_id"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
            <option
              v-for="eventType in eventTypes"
              :key="eventType.id"
              :value="eventType.id"
            >
              {{ eventType.name }}
            </option>
            <!-- Add more options as needed -->
          </select>
        </div>
        <div>
          <label
            for="date"
            class="block text-sm font-medium text-gray-700"
          >Event Date</label>
          <input
            id="date"
            v-model="form.date"
            type="date"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
        </div>
        <div>
          <label
            for="start_time"
            class="block text-sm font-medium text-gray-700"
          >Start Time</label>
          <input
            id="start_time"
            v-model="form.start_time"
            type="time"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
        </div>
        <div>
          <label
            for="end_time"
            class="block text-sm font-medium text-gray-700"
          >End Time</label>
          <input
            id="end_time"
            v-model="form.end_time"
            type="time"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
        </div>
        <div>
          <LocationAutocomplete
            v-model="form.venue_name"
            name="venue_name"
            label="Venue"
            placeholder="Enter a venue name or address"
            @location-selected="(locationData) => handleLocationSelected(locationData)"
          />
        </div>
        <div>
          <TextInput
            v-model="form.venue_address"
            name="venue_address"
            label="Venue Address"
          />
        </div>
        <div>
          <label
            for="price"
            class="block text-sm font-medium text-gray-700"
          >Price</label>
          <input
            id="price"
            v-model="form.price"
            type="number"
            step="0.01"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
        </div>
        <div>
          <label
            for="status"
            class="block text-sm font-medium text-gray-700"
          >Status</label>
          <select
            id="status"
            v-model="form.status"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
            <option value="draft">
              Draft
            </option>
            <option value="pending">
              Pending
            </option>
            <option value="confirmed">
              Confirmed
            </option>
            <option value="cancelled">
              Cancelled
            </option>
          </select>
        </div>
        <div>
          <label
            for="contract_option"
            class="block text-sm font-medium text-gray-700"
          >Contract Option</label>
          <select
            id="contract_option"
            v-model="form.contract_option"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
            <option value="default">
              Default (Automatic)
            </option>
            <option value="none">
              None
            </option>
            <option value="external">
              External
            </option>
          </select>
        </div>
      </div>
      <div>
        <label
          for="notes"
          class="block text-sm font-medium text-gray-700"
        >Notes</label>
        <textarea
          id="notes"
          v-model="form.notes"
          rows="3"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
        />
      </div>
      <div class="flex justify-end space-x-4">
        <button
          type="button"
          @click="cancelBooking"
          class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150"
        >
          Cancel Booking
        </button>
        <button
          type="submit"
          class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150"
        >
          Update Booking
        </button>
      </div>
    </form>
  </Container>
</template>
  
  <script setup>
  import { computed } from 'vue'
  import { useForm } from '@inertiajs/vue3'
  import Container from '@/Components/Container.vue'
  import { useStore } from 'vuex';
  import LocationAutocomplete from '@/Components/LocationAutocomplete.vue';
  import TextInput from '@/Components/TextInput.vue';  
  
  const props = defineProps({
    booking: {
      type: Object,
      required: true
    },
    band: {
      type: Object,
      required: true
    }
  })

  const eventTypes = computed(() => {
    return store.getters['eventTypes/getAllEventTypes']
  })

  const store = useStore();
  
  const form = useForm({
    name: props.booking.name,
    event_type_id: props.booking.event_type_id,
    date: props.booking.date,
    start_time: props.booking.start_time,
    end_time: props.booking.end_time,
    venue_name: props.booking.venue_name,
    venue_address: props.booking.venue_address,
    price: props.booking.price,
    status: props.booking.status,
    contract_option: props.booking.contract_option,
    notes: props.booking.notes,
  })
  
  const updateBooking = () => {
    form.put(route('bands.booking.update', [props.band,props.booking], form), {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const cancelBooking = () => {
  if (confirm('Are you sure you want to cancel this booking?')) {
    form.post(route('Cancel Booking', [props.band, props.booking]), {
      preserveScroll: true,
      preserveState: true,
    })
  }
}

  const handleLocationSelected = (locationData) => {
    // Handle full location data here
    form.venue_address = locationData.result.formatted_address
  };
</script>