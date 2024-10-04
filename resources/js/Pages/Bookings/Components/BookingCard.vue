<template>
  <li class="booking-card">
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
      <hr v-if="showFuelGauge" class="my-2">
      <div v-if="showFuelGauge" class="fuel-gauge">
        <div class="fuel-label">
          Contract Urgency:
          <span class="sent-date">Sent on {{ booking.contract.updated_at }}</span>
        </div>
        <div class="fuel-bar" :style="{ width: `${fuelLevel}%`, backgroundColor: fuelColor }"></div>
      </div>
    </Link>
  </li>
</template>

<script setup>
import { computed } from 'vue';
import { DateTime } from 'luxon';

const props = defineProps({
  booking: {
    type: Object,
    required: true
  }
});

const formatDate = (date) => {
  return DateTime.fromISO(date).toFormat('LLL d, yyyy');
};

const showFuelGauge = computed(() => {
  console.log(props.booking.contract_option, props.booking.status);
  return props.booking.contract_option === 'default' && props.booking.status === 'pending' && props.booking.contract;
});

const daysSinceSent = computed(() => {
  if (!showFuelGauge.value) return 0;
  const now = DateTime.now();
  const updatedAt = DateTime.fromFormat(props.booking.contract.updated_at, 'yyyy-MM-dd hh:mm a');
  return now.diff(updatedAt, 'days').days;
});

const fuelLevel = computed(() => {
  if (!showFuelGauge.value) return 0;
  // Always show some level of the bar
  return Math.max(5, 100 - (daysSinceSent.value * 10));
});

const fuelColor = computed(() => {
  if (daysSinceSent.value < 7) return '#48bb78'; // Green
  if (daysSinceSent.value < 14) return '#ecc94b'; // Yellow
  return '#f56565'; // Red
});
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

.fuel-gauge {
  margin-top: 0.5rem;
}

.fuel-label {
  font-size: 0.75rem;
  color: #4a5568;
  margin-bottom: 0.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sent-date {
  font-style: italic;
}

.fuel-bar {
  height: 0.5rem;
  border-radius: 0.25rem;
  transition: width 0.3s ease, background-color 0.3s ease;
}
</style>