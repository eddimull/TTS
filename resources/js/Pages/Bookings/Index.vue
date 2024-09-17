<template>
  <Container class="md:container md:mx-auto">
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <div class="flex justify-between items-center mb-4">
              <span class="text-xl font-bold">Booking Kanban Board</span>
              <div class="flex items-center">
                <input 
                  v-model="searchTerm" 
                  type="text" 
                  placeholder="Search bookings..." 
                  class="px-3 py-2 border rounded-md mr-4"
                >
                <label class="inline-flex items-center">
                  <input
                    v-model="showPastBookings"
                    type="checkbox"
                    class="form-checkbox h-5 w-5 text-blue-600"
                  >
                  <span class="ml-2 text-gray-700">Show only past bookings</span>
                </label>
              </div>
            </div>
            <div>
              <Link
                v-for="band in bands"
                :key="band.id"
                :href="route('Create Booking', { band: band.id })"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2 mb-2 inline-block"
              >
                Create Booking for {{ band.name }}
              </Link>
            </div>
          </div>
          <div class="flex overflow-x-auto p-4">
            <div
              v-for="status in statuses"
              :key="status"
              class="flex-shrink-0 grow w-64 mr-4"
            >
              <h3 class="font-bold mb-2 capitalize">
                {{ status }}
              </h3>
              <ul class="bg-gray-100 rounded p-2 min-h-[200px] max-h-[1000px] overflow-y-auto">
                <BookingCard
                  v-for="booking in getFilteredBookings(status)"
                  :key="booking.id"
                  :booking="booking"
                />
                <li
                  v-if="getFilteredBookings(status).length === 0"
                  class="text-gray-500 italic"
                >
                  No bookings in this status
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Container>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { DateTime } from 'luxon'
import BookingCard from './Components/BookingCard.vue';

export default {
  components: {
    BookingCard
  },
  layout: BreezeAuthenticatedLayout,
  props: {
    bookings: Array,
    bands: Object,
  },
  data() {
    return {
      statuses: ['draft', 'pending', 'confirmed'],
      showPastBookings: false,
      searchTerm: ''
    }
  },
  computed: {
    currentDate() {
      return DateTime.now()
    },
    twoWeeksAgo() {
      return this.currentDate.minus({ weeks: 2 })
    }
  },
  methods: {
    getFilteredBookings(status) {
      return this.bookings.filter(booking => {
        const bookingDate = DateTime.fromISO(booking.date)
        const isCorrectStatus = booking.status.toLowerCase() === status.toLowerCase()
        const matchesSearch = this.searchBooking(booking)
        
        const dateCondition = this.showPastBookings
          ? bookingDate < this.currentDate
          : bookingDate > this.twoWeeksAgo

        return isCorrectStatus && dateCondition && matchesSearch
      })
    },

    searchBooking(booking) {
      if (!this.searchTerm) return true

      const searchLower = this.searchTerm.toLowerCase()
      return (
        booking.name.toLowerCase().includes(searchLower) ||
        booking.venue_name.toLowerCase().includes(searchLower) ||
        booking.contacts.some(contact => contact.name.toLowerCase().includes(searchLower)) ||
        (booking.notes && booking.notes.toLowerCase().includes(searchLower))
      )
    }
  }
}
</script>