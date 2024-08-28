<template>
  <Container class="md:container md:mx-auto">
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200 flex justify-between items-center">
            <span>List of bookings.</span>
            <Link
              v-for="band in bands"
              :key="band.id"
              :href="route('bands.booking.create', { band: band.id })"
              class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
            >
              Create Booking for {{ band.name }}
            </Link>
          </div>
          <ul>
            <li v-if="bookings.length === 0">
              No bookings found.
            </li>
            <li
              v-for="booking in bookings"
              :key="booking.id"
              class="p-4 border-b border-gray-200"
            >
              {{ booking.start_time }} - {{ booking.end_time }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </Container>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
export default {
  layout: BreezeAuthenticatedLayout,
  props: {
    bookings: Array,
    bands: Object,
  },
}
</script>