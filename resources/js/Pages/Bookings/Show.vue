<template>
  <Container>
    <h1>Booking Details</h1>
    <div v-if="booking">
      <p><strong>ID:</strong> {{ booking.id }}</p>
      <p><strong>Date:</strong> {{ booking.date }}</p>
      <p><strong>Venue:</strong> {{ booking.venue }}</p>
      <!-- Add more booking details as needed -->

      <h2>Band Information</h2>
      <div v-if="booking.band">
        <p><strong>Band Name:</strong> {{ booking.band.name }}</p>
        <!-- Add more band details as needed -->
      </div>
      <p v-else>
        No band information available
      </p>
    </div>
  </Container>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'

defineOptions({
  layout: BreezeAuthenticatedLayout,
})

const props = defineProps({
  booking: Object,
  band: Object,
})
</script>