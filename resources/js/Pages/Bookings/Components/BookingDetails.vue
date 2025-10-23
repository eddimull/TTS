<template>
  <Container class="p-4">
    <div class="space-y-6">
      <!-- Header Section with Title and Status -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
        <div class="flex justify-between items-start mb-4">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-50">
            {{ booking.name }}
          </h1>
          <span
            :class="statusClass"
            class="px-4 py-2 rounded-full text-sm font-semibold uppercase tracking-wide"
          >
            {{ booking.status }}
          </span>
        </div>
        
        <!-- Event Type Badge -->
        <div
          v-if="eventType"
          class="inline-block bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full text-sm font-medium"
        >
          <i class="pi pi-tag mr-1" />
          {{ eventType.name }}
        </div>
      </div>

      <!-- Date & Time Section -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-50 mb-4 flex items-center">
          <i class="pi pi-calendar mr-2" />
          Date & Time
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Date
            </div>
            <div class="text-lg font-medium text-gray-900 dark:text-gray-50">
              {{ formatDate(booking.date) }}
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Start Time
            </div>
            <div class="text-lg font-medium text-gray-900 dark:text-gray-50">
              {{ formatTime(booking.start_time) }}
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              End Time
            </div>
            <div class="text-lg font-medium text-gray-900 dark:text-gray-50">
              {{ formatTime(booking.end_time) }}
            </div>
          </div>
        </div>
        <div
          v-if="duration"
          class="mt-4 inline-block bg-gray-100 dark:bg-slate-700 px-3 py-1 rounded text-sm"
        >
          <i class="pi pi-clock mr-1" />
          Duration: <strong>{{ duration }}</strong>
        </div>
      </div>

      <!-- Venue Section -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-50 mb-4 flex items-center">
          <i class="pi pi-map-marker mr-2" />
          Venue Information
        </h2>
        <div class="space-y-3">
          <div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Venue Name
            </div>
            <div class="text-lg font-medium text-gray-900 dark:text-gray-50">
              {{ booking.venue_name || 'Not specified' }}
            </div>
          </div>
          <div v-if="booking.venue_address">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Address
            </div>
            <div class="text-lg text-gray-900 dark:text-gray-50">
              {{ booking.venue_address }}
            </div>
          </div>
        </div>
      </div>

      <!-- Financial Section -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-50 mb-4 flex items-center">
          <i class="pi pi-dollar mr-2" />
          Financial Details
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
            <div class="text-sm text-green-600 dark:text-green-400 mb-1">
              Agreed Price
            </div>
            <div class="text-3xl font-bold text-green-700 dark:text-green-300">
              ${{ formatPrice(booking.price) }}
            </div>
          </div>
          <div
            v-if="booking.amountPaid !== undefined"
            class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800"
          >
            <div class="text-sm text-blue-600 dark:text-blue-400 mb-1">
              Amount Paid
            </div>
            <div class="text-3xl font-bold text-blue-700 dark:text-blue-300">
              ${{ formatPrice(booking.amountPaid) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Contract Section -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-50 mb-4 flex items-center">
          <i class="pi pi-file-edit mr-2" />
          Contract Information
        </h2>
        <div class="space-y-3">
          <div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Contract Option
            </div>
            <div class="text-lg font-medium text-gray-900 dark:text-gray-50">
              <span
                v-if="booking.contract_option === 'default'"
                class="inline-flex items-center bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded"
              >
                <i class="pi pi-check-circle mr-1" />
                Default (Automatic)
              </span>
              <span
                v-else-if="booking.contract_option === 'external'"
                class="inline-flex items-center bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 px-3 py-1 rounded"
              >
                <i class="pi pi-external-link mr-1" />
                External
              </span>
              <span
                v-else
                class="inline-flex items-center bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-3 py-1 rounded"
              >
                <i class="pi pi-times-circle mr-1" />
                None
              </span>
            </div>
          </div>
          <div
            v-if="booking.contract"
            class="text-sm text-gray-600 dark:text-gray-400"
          >
            <i class="pi pi-info-circle mr-1" />
            Contract status and details available in the Contract tab
          </div>
        </div>
      </div>

      <!-- Contacts Section -->
      <div
        v-if="booking.contacts && booking.contacts.length > 0"
        class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6"
      >
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-50 mb-4 flex items-center">
          <i class="pi pi-users mr-2" />
          Contacts
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="contact in booking.contacts"
            :key="contact.id"
            class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 hover:shadow-md transition-shadow"
          >
            <div class="font-semibold text-gray-900 dark:text-gray-50 mb-2">
              {{ contact.name }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
              <div
                v-if="contact.email"
                class="flex items-center"
              >
                <i class="pi pi-envelope mr-2" />
                <a
                  :href="`mailto:${contact.email}`"
                  class="text-blue-600 dark:text-blue-400 hover:underline"
                >
                  {{ contact.email }}
                </a>
              </div>
              <div
                v-if="contact.phonenumber"
                class="flex items-center"
              >
                <i class="pi pi-phone mr-2" />
                {{ contact.phonenumber }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Notes Section -->
      <div
        v-if="booking.notes"
        class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6"
      >
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-50 mb-4 flex items-center">
          <i class="pi pi-file-edit mr-2" />
          Notes
        </h2>
        <div class="prose dark:prose-invert max-w-none">
          <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
            {{ booking.notes }}
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-4">
        <Button
          label="Edit Booking"
          icon="pi pi-pencil"
          severity="secondary"
          @click="editBooking"
        />
        <Button
          v-if="props.booking.status !== 'confirmed'"
          label="Delete Booking"
          icon="pi pi-trash"
          severity="danger"
          outlined
          @click="deleteBooking"
        />
        <Button
          v-if="props.booking.status === 'confirmed'"
          label="Cancel Booking"
          icon="pi pi-times"
          severity="danger"
          outlined
          @click="cancelBooking"
        />
      </div>
    </div>
  </Container>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Container from '@/Components/Container.vue'
import Button from 'primevue/button'
import { useStore } from 'vuex'
import { DateTime } from 'luxon'

const props = defineProps({
  booking: {
    type: Object,
    required: true
  },
  band: {
    type: Object,
    required: true
  }
})

const store = useStore()

const eventType = computed(() => {
  const types = store.getters['eventTypes/getAllEventTypes']
  return types.find(type => type.id === props.booking.event_type_id)
})

const statusClass = computed(() => {
  const statusClasses = {
    draft: 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    pending: 'bg-yellow-200 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    confirmed: 'bg-green-200 text-green-800 dark:bg-green-900 dark:text-green-200',
    cancelled: 'bg-red-200 text-red-800 dark:bg-red-900 dark:text-red-200'
  }
  return statusClasses[props.booking.status] || statusClasses.draft
})

const duration = computed(() => {
  if (!props.booking.start_time || !props.booking.end_time) return null
  
  const start = DateTime.fromFormat(props.booking.start_time, 'HH:mm:ss')
  const end = DateTime.fromFormat(props.booking.end_time, 'HH:mm:ss')
  
  if (!start.isValid || !end.isValid) return null
  
  const diff = end.diff(start, ['hours', 'minutes'])
  const hours = Math.floor(diff.hours)
  const minutes = Math.round(diff.minutes)
  
  if (hours > 0 && minutes > 0) {
    return `${hours}h ${minutes}m`
  } else if (hours > 0) {
    return `${hours} hours`
  } else if (minutes > 0) {
    return `${minutes} minutes`
  }
  return null
})

const formatDate = (date) => {
  if (!date) return 'Not specified'
  return DateTime.fromISO(date).toFormat('EEEE, MMMM d, yyyy')
}

const formatTime = (time) => {
  if (!time) return 'Not specified'
  const dt = DateTime.fromFormat(time, 'HH:mm:ss')
  return dt.isValid ? dt.toFormat('h:mm a') : time
}

const formatPrice = (price) => {
  if (price === null || price === undefined) return '0.00'
  return parseFloat(price).toFixed(2)
}

const editBooking = () => {
  // Navigate to Booking Details with edit mode query param or use an edit component
  router.visit(route('Booking Details', [props.band.id, props.booking.id]) + '?edit=true')
}

const deleteBooking = () => {
  if (confirm('Are you sure you want to delete this booking? This action cannot be undone.')) {
    router.delete(route('bands.booking.destroy', [props.band.id, props.booking.id]), {
      preserveScroll: false,
    })
  }
}

const cancelBooking = () => {
  if (confirm('Are you sure you want to cancel this booking?')) {
    router.post(route('Cancel Booking', [props.band.id, props.booking.id]), {}, {
      preserveScroll: true,
      preserveState: true,
    })
  }
}
</script>

<style scoped>
.prose {
  max-width: 100%;
}
</style>
