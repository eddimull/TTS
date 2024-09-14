<template>
  <BookingLayout>
    <div class="max-w-4xl mx-auto p-4">
      <h2 class="text-2xl font-bold mb-4">
        Contacts for {{ booking.name }}
      </h2>
      
      <!-- Display existing contacts -->
      <div
        v-if="booking.contacts.length > 0"
        class="mb-8"
      >
        <h3 class="text-xl font-semibold mb-2">
          Existing Contacts
        </h3>
        <div
          v-for="contact in booking.contacts"
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
      
      <!-- New contact form -->
      <h3 class="text-xl font-semibold mb-2">
        Add New Contact
      </h3>
      <NewContactForm
        :band-id="band.id"
        :booking-id="booking.id"
      />
    </div>
  </BookingLayout>
</template>

<script setup>
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
</script>