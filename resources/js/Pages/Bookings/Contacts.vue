<template>
  <BookingLayout :booking="booking">
    <div class="max-w-4xl mx-auto p-4">
      <!-- Existing Booking Contacts -->
      <div
        v-if="localBookingContacts.length > 0"
        class="mb-8"
      >
        <h3 class="text-xl font-semibold mb-2">
          Existing Booking Contacts
        </h3>
        <div
          v-for="contact in localBookingContacts"
          :key="contact.id"
          class="bg-gray-100 p-4 rounded-lg mb-2"
        >
          <ContactCard 
            :contact="contact" 
            :band-id="band.id" 
            :booking-id="booking.id"
          />
        </div>
      </div>
      <div v-else>
        <p class="text-center m-8">
          No contacts found for this booking.
        </p>
      </div>
      
      <!-- Reuse Existing Band Contacts -->
      <div class="mb-8">
        <h3 class="text-xl font-semibold mb-2">
          Reuse Existing Band Contacts
        </h3>
        <div v-if="availableBandContacts.length > 0">
          <select
            v-model="selectedContact"
            class="w-full p-2 border rounded"
            @change="addExistingContact"
          >
            <option value="">
              Select a contact
            </option>
            <option
              v-for="contact in availableBandContacts"
              :key="contact.id"
              :value="contact.id"
            >
              {{ contact.name }} - {{ contact.email }} <span v-if="contact.booking_history.length > 1">(Used {{ contact.booking_history.length }} times)</span>
            </option>
          </select>
        </div>
        <p
          v-else
          class="text-center m-4"
        >
          No additional band contacts available.
        </p>
      </div>
      
      <!-- New contact form -->
      <h3 class="text-xl font-semibold mb-2">
        Add New Contact
      </h3>
      <NewContactForm
        :band-id="band.id"
        :booking-id="booking.id"
        @contact-added="onContactAdded"
      />
    </div>
  </BookingLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ContactCard from './Components/ContactCard.vue';
import BookingLayout from './Layout/BookingLayout.vue';
import NewContactForm from './Components/NewContactForm.vue';

const props = defineProps({
  booking: {
    type: Object,
    required: true
  },
  band: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['update:booking']);

const localBookingContacts = ref([...props.booking.contacts]);
const bandContacts = ref([]);
const selectedContact = ref('');

const availableBandContacts = computed(() => {
  return bandContacts.value.filter(
    contact => !localBookingContacts.value.some(bc => bc.id === contact.id)
  );
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    role: '',
    notes: '',
    is_primary: localBookingContacts.value.length === 0
});

onMounted(async () => {
  await fetchBandContacts();
});

async function fetchBandContacts() {
  try {
    const response = await axios.get(`/api/bands/${props.band.id}/contacts`);
    bandContacts.value = response.data;
  } catch (error) {
    console.error('Error fetching band contacts:', error);
  }
}

function addExistingContact() {
  if (selectedContact.value) {
    
    const foundContact = bandContacts.value.find(c => c.id === selectedContact.value);

    form.name = foundContact.name;
    form.email = foundContact.email;
    form.phone = foundContact.phone.toString();

    form.post(route('Store Booking Contact', [props.band.id, props.booking.id]), {
      preserveState: false,
      preserveScroll: true,
      onSuccess: (response) => {
        selectedContact.value = '';
        emitBookingUpdate();
      },
    });
  }
}

function onContactAdded(newContact) {
  localBookingContacts.value.push(newContact);
  emitBookingUpdate();
}

function emitBookingUpdate() {
  emit('update:booking', {
    ...props.booking,
    contacts: localBookingContacts.value
  });
}
</script>