<template>
  <div class="bg-white shadow-md rounded-lg p-4 mb-4">
    <div v-if="!isEditing">
      <p><strong>Name:</strong> {{ contact.name }}</p>
      <p><strong>Email:</strong> {{ contact.email }}</p>
      <p v-if="contact.phone">
        <strong>Phone:</strong> {{ contact.phone }}
      </p>
      <p><strong>Role:</strong> {{ contact.pivot.role }}</p>
      <p><strong>Primary:</strong> {{ contact.pivot.is_primary ? 'Yes' : 'No' }}</p>
      <p v-if="contact.pivot.notes">
        <strong>Notes:</strong> {{ contact.pivot.notes }}
      </p>
      <div class="mt-4 space-x-2">
        <button
          class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
          @click="startEditing"
        >
          Edit
        </button>
        <button
          class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600"
          @click="deleteContact"
        >
          Delete
        </button>
      </div>
    </div>
  
    <form
      v-else
      class="space-y-4"
      @submit.prevent="updateContact"
    >
      <div>
        <label
          for="name"
          class="block text-sm font-medium text-gray-700"
        >Name</label>
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
        >Email</label>
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
        >Phone</label>
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
      <div class="flex items-center">
        <input
          id="is_primary"
          v-model="form.is_primary"
          type="checkbox"
          class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
        >
        <label
          for="is_primary"
          class="ml-2 block text-sm text-gray-900"
        >Primary Contact</label>
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
      <div class="space-x-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
        >
          {{ form.processing ? 'Saving...' : 'Save' }}
        </button>
        <button
          type="button"
          class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
          @click="cancelEditing"
        >
          Cancel
        </button>
      </div>
    </form>
  </div>
</template>
  
  <script setup>
  import { ref } from 'vue';
  import { router, useForm } from '@inertiajs/vue3';
  import Swal from 'sweetalert2';
  
  const props = defineProps({
    contact: {
      type: Object,
      required: true
    },
    bandId: {
      type: Number,
      required: true
    },
    bookingId: {
      type: Number,
      required: true
    }
  });
  
  const isEditing = ref(false);
  
  const form = useForm({
    name: props.contact.name,
    email: props.contact.email,
    phone: props.contact.phone || '',
    role: props.contact.pivot.role,
    is_primary: props.contact.pivot.is_primary,
    notes: props.contact.pivot.notes || ''
  });
  
  const startEditing = () => {
    isEditing.value = true;
  };
  
  const cancelEditing = () => {
    isEditing.value = false;
    form.reset();
  };
  
  const updateContact = () => {
    form.put(route('Update Booking Contact', [props.bandId, props.bookingId, props.contact.pivot.id]), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        isEditing.value = false;
      },
    });
  };
  
  const deleteContact = () => {
    Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('Delete Booking Contact', { band: props.bandId, booking: props.bookingId, contact: props.contact.pivot.id})), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Swal.fire(
            'Deleted!',
            'The contact has been deleted.',
            'success'
          );
        },
        onError: () => {
          Swal.fire(
            'Error!',
            'There was a problem deleting the contact.',
            'error'
          );
        },
      };
    }
  });
};
  </script>