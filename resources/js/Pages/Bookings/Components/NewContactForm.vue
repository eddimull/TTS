<template>
  <form
    class="space-y-4"
    @submit.prevent="submitForm"
  >
    <div>
      <label
        for="name"
        class="block text-sm font-medium text-gray-700"
      >Name *</label>
      <input
        id="name"
        v-model="form.name"
        type="text"
        required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
      >
    </div>
      
    <div>
      <label
        for="email"
        class="block text-sm font-medium text-gray-700"
      >Email *</label>
      <input
        id="email"
        v-model="form.email"
        type="email"
        required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
      >
    </div>
      
    <div>
      <label
        for="phone"
        class="block text-sm font-medium text-gray-700"
      >Phone Number</label>
      <input
        id="phone"
        v-model="form.phone"
        type="tel"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
      >
    </div>
  
    <div>
      <label
        for="role"
        class="block text-sm font-medium text-gray-700"
      >Role</label>
      <input
        id="role"
        v-model="form.role"
        type="text"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
      >
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
      
    <div class="flex items-center">
      <input
        id="is_primary"
        v-model="form.is_primary"
        type="checkbox"
        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-offset-0 focus:ring-indigo-200 focus:ring-opacity-50"
      >
      <label
        for="is_primary"
        class="ml-2 block text-sm text-gray-900"
      >Primary Contact</label>
    </div>
      
    <div>
      <button
        type="submit"
        :disabled="form.processing"
        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
      >
        {{ form.processing ? 'Adding...' : 'Add Contact' }}
      </button>
    </div>
  </form>
</template>
  
  <script setup>
  import { useForm } from '@inertiajs/vue3';
  
  const props = defineProps({
    bandId: {
      type: Number,
      required: true
    },
    bookingId: {
      type: Number,
      required: true
    }
  });
  
  const form = useForm({
    name: '',
    email: '',
    phone: '',
    role: '',
    notes: '',
    is_primary: false
  });
  
  const submitForm = () => {
    form.post(route('Store Booking Contact', [props.bandId, props.bookingId]), {
      preserveScroll: true,
      preserveState: true,
      resetOnSuccess: true
    });
  };
  </script>