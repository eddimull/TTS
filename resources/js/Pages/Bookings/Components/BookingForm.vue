<template>
  <Container>
    <h1 class="text-2xl font-bold mb-6">
      Booking Details
    </h1>
    <form
      class="space-y-6"
      @submit.prevent="updateBooking"
    >
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label
            for="name"
            class="block text-sm font-medium text-gray-700"
          >Name</label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
        </div>
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
            for="event_date"
            class="block text-sm font-medium text-gray-700"
          >Event Date</label>
          <input
            id="event_date"
            v-model="form.event_date"
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
          <label
            for="venue_name"
            class="block text-sm font-medium text-gray-700"
          >Venue Name</label>
          <input
            id="venue_name"
            v-model="form.venue_name"
            type="text"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
        </div>
        <div>
          <label
            for="venue_address"
            class="block text-sm font-medium text-gray-700"
          >Venue Address</label>
          <input
            id="venue_address"
            v-model="form.venue_address"
            type="text"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          >
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
            <option value="none">
              None
            </option>
            <option value="signed">
              Signed
            </option>
            <option value="pending">
              Pending
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
      <div class="flex justify-end">
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
    event_date: props.booking.event_date,
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
  </script>