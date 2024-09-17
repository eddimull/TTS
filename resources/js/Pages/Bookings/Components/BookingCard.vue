<template>
  <li
    class="booking-card"
  >
    <Link 
      :href="route('Booking Details', { band: booking.band_id, booking: booking.id })"
      class="booking-card-title"
    >
      {{ booking.name }}
      <div class="booking-card-info">
        Date: {{ formatDate(booking.date) }}<br>
        Venue: {{ booking.venue_name }}
      </div>
      <hr class="my-2">
      <div class="booking-card-info">
        Contacts: 
        <ul>
          <li
            v-for="contact in booking.contacts"
            :key="contact.id"
          >
            {{ contact.name }} - {{ contact.email }}
          </li>
        </ul>
      </div>
    </Link>
  </li>
</template>
<script setup>
import { DateTime } from 'luxon';
    const props = defineProps({
        booking: {
            type: Object,
            required: true
        }
    })

    const formatDate = (date) => {
        return DateTime.fromISO(date).toFormat('LLL d, yyyy')
    }
</script>
<style scoped>
.booking-card {
  background-color: white;
  border-radius: 0.375rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
  padding: 0.5rem;
  margin-bottom: 0.5rem;
  transition: all 0.3s ease;
  cursor: pointer;
}

.booking-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.booking-card-title {
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 0.25rem;
}

.booking-card-info {
  font-size: 0.75rem;
  color: #718096;
}
</style>