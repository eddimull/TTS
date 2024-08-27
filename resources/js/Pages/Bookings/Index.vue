<template>
  <div>
    <h1>Bookings</h1>
    <ul v-if="bookings.length">
      <li
        v-for="booking in bookings"
        :key="booking.id"
      >
        Booking #{{ booking.id }} - {{ booking.date }}
      </li>
    </ul>
    <p v-else>
      No bookings found.
    </p>
  </div>
</template>
  
  <script>
  export default {
    props: {
      bookings: {
        type: Array,
        default: () => [],
      }
    },
  }
  </script>