<template>
  <div class="bg-white dark:bg-slate-600 shadow-md rounded-lg p-4 mb-4">
    <div
      v-if="!isEditing"
      class="relative"
    >
      <Menu
        ref="menu"
        :model="menuItems"
        :popup="true"
      />
      <div class="absolute top-2 right-2">
        <Button
          icon="pi pi-ellipsis-v"
          aria-haspopup="true"
          aria-controls="overlay_menu"
          class="p-button-text p-button-plain"
          @click="toggleMenu"
        />
      </div>


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
      <div
        v-if="contact.booking_history"
        class="pt-4 border-t mt-4"
      >
        <strong>Booking History:</strong>
        <ul class="indent-2 list-inside">
          <li
            v-for="history in contact.booking_history"
            :key="history.booking_id"
          >
            <NavLink
              
              :href="route('Booking Details',[contact.band_id,history.booking_id])"
            >
              <strong>{{ history.booking_name }}</strong> - {{ history.date }}
            </NavLink>
          </li>
        </ul>
      </div>
      <div
        v-if="false"
        class="mt-4 space-x-2"
      >
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
        <Input
          id="name"
          v-model="form.name"
          label="name"
          type="text"
          required
          class="mt-1 block w-full rounded-md"
        />
      </div>
      <div>
        <Input
          id="email"
          v-model="form.email"
          label="email"
          type="email"
          required
          class="mt-1 block w-full rounded-md"
        />
      </div>
      <div>
        <Input
          id="phone"
          v-model="form.phone"
          label="phone"
          type="tel"
          class="mt-1 block w-full rounded-md"
        />
      </div>
      <div>
        <Input
          id="role"
          v-model="form.role"
          label="Role"
          type="text"
          class="mt-1 block w-full rounded-md"
        />
      </div>
      <div class="flex my-2 items-center">
        <Checkbox
          id="is_primary"
          v-model="form.is_primary"
          type="checkbox"
          label="Primary Contact"
        />
      </div>
      <div>
        <TextArea
          v-model="form.notes"
          label="Notes"
          name="notes"          
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
  import Menu from 'primevue/menu';
  import Button from 'primevue/button';
  import NavLink from '@/Components/NavLink.vue';
  import Input from '@/Components/Input.vue';
  import Label from '@/Components/Label.vue';
  import Checkbox from '@/Components/Checkbox.vue';
  import TextArea from '@/Components/TextArea.vue';
  
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

  const menu = ref();

  const menuItems = [
    {
      label: 'Edit',
      icon: 'pi pi-pencil',
      command: () => startEditing()
    },
    {
      label: 'Delete',
      icon: 'pi pi-trash',
      command: () => deleteContact()
    }
  ];

  const toggleMenu = (event) => {
    menu.value.toggle(event);
  };
  
  const form = useForm({
    name: props.contact.name,
    email: props.contact.email,
    phone: props.contact.phone || '',
    role: props.contact.pivot.role,
    is_primary: Boolean(props.contact.pivot.is_primary),
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
        router.delete(route('Delete Booking Contact', { 
          band: props.bandId, 
          booking: props.bookingId, 
          contact: props.contact.pivot.id
        }), {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            router.reload();
          },
          onError: () => {
            Swal.fire(
              'Error!',
              'There was a problem deleting the contact.',
              'error'
            );
          },
        });
      }
    });
  };

  </script>