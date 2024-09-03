<template>
  <div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">
      Create New Booking for {{ band.name }}
    </h1>
    <form @submit.prevent="submitForm">
      <div class="space-y-6">
        <div>
          <label
            for="name"
            class="block text-sm font-medium text-gray-700"
          >Booking Name</label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            required
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
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
            required
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          >
            <option value="">
              Select an event type
            </option>
            <option
              v-for="event in eventTypes"
              :key="event.id"
              :value="event.id"
            >
              {{ event.name }}
            </option>
          </select>
        </div>
  
        <div>
          <label
            for="event_date"
            class="block text-sm font-medium text-gray-700"
          >Date</label>
          <input
            id="event_date"
            v-model="form.event_date"
            type="date"
            required
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
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
            required
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          >
        </div>

        <div>
          <label
            for="duration"
            class="block text-sm font-medium text-gray-700"
          >Duration (hours)</label>
          <input
            id="duration"
            v-model="form.duration"
            type="number"
            required
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          >
        </div>
  
        <div>
          <label
            for="price"
            class="block text-sm font-medium text-gray-700"
          >Price</label>
          <div class="mt-1 relative rounded-md shadow-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="text-gray-500 sm:text-sm">$</span>
            </div>
            <input
              id="price"
              v-model="form.price"
              type="number"
              required
              min="0"
              step="0.01"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 pl-7 pr-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
          </div>
        </div>
        <ContractOptions v-model="form.contract_option" />
        <div>
          <button
            type="submit"
            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            Create Booking
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
  
  <script>
  import { ref } from 'vue'
  import ContractOptions from './ContractOptions.vue'
  import { useForm } from '@inertiajs/vue3'
  
  export default {
    components: {
      ContractOptions
    },
    props: {
      band: {
        type: Object,
        required: true
      },
      eventTypes: {
        type: Array,
        required: true
      }

    },
    setup(props) {
      const form = useForm({
        name: '',
        event_type_id: '',
        event_date: '',
        start_time: '19:00',
        duration: 4,
        price: '',
        contract_option: ''
      })
  
      const submitForm = () => {
        form.post(route('bands.booking.store', props.band.id), {
          preserveScroll: true,
          preserveState: true
        })
      }
  
      return {
        form,
        submitForm
      }
    }
  }
  </script>